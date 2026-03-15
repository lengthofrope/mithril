
@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();

    // Get current path
    $currentPath = request()->path();
@endphp

<aside id="sidebar"
    class="fixed flex flex-col mt-0 top-0 px-5 left-0 bg-gray-25 dark:bg-gray-800 dark:border-gray-700 text-gray-900 h-screen z-99999 border-r border-gray-200"
    x-init="requestAnimationFrame(() => $el.classList.add('transition-all', 'duration-300', 'ease-in-out'))"
    x-data="{
        openSubmenus: {},
        init() {
            this.initializeActiveMenus();
        },
        initializeActiveMenus() {
            const currentPath = '{{ $currentPath }}';
            const pathname = window.location.pathname;

            @foreach ($menuGroups as $groupIndex => $menuGroup)
                @foreach ($menuGroup['items'] as $itemIndex => $item)
                    @if (isset($item['subItems']))
                        @foreach ($item['subItems'] as $subItem)
                            if (currentPath === '{{ ltrim($subItem['path'], '/') }}' ||
                                pathname === '{{ $subItem['path'] }}' ||
                                pathname.startsWith('{{ $subItem['path'] }}/')) {
                                this.openSubmenus['{{ $groupIndex }}-{{ $itemIndex }}'] = true;
                            }
                        @endforeach
                    @endif
                @endforeach
            @endforeach
        },
        toggleSubmenu(groupIndex, itemIndex, firstSubItemPath = null) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            // Close all other submenus when opening a new one
            if (newState) {
                this.openSubmenus = {};
            }

            this.openSubmenus[key] = newState;

            if (newState && firstSubItemPath) {
                window.location.href = firstSubItemPath;
            }
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            return this.openSubmenus[key] || false;
        },
        isActive(path) {
            return window.location.pathname === path || '{{ $currentPath }}' === path.replace(/^\//, '');
        },
        get isCollapsed() {
            return !$store.sidebar.isExpanded && !$store.sidebar.isMobileOpen;
        }
    }"
    :class="{
        'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen,
        'w-[90px]': isCollapsed,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }">
    <!-- Logo Section -->
    <div class="pt-6 pb-7 flex"
        :class="isCollapsed ? 'xl:justify-center' : 'justify-start'">
        <a href="/">
            <span x-show="!isCollapsed">
                <x-tl.logo />
            </span>
            <img x-show="isCollapsed"
                src="/images/logo/logo-icon.svg" alt="Logo" width="32" height="32" />

        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        <!-- Menu Group Title -->
                        <h2 class="mb-4 text-xs uppercase flex leading-[20px] text-gray-400"
                            :class="isCollapsed ? 'lg:justify-center' : 'justify-start'">
                            <template x-if="!isCollapsed">
                                <span>{{ $menuGroup['title'] }}</span>
                            </template>
                            <template x-if="isCollapsed">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z" fill="currentColor"/>
                                </svg>
                            </template>
                        </h2>

                        <!-- Menu Items -->
                        <ul class="flex flex-col gap-1">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                @if (!empty($item['separator']))
                                    <li class="my-1">
                                        <hr class="border-gray-200 dark:border-gray-700" />
                                    </li>
                                    @continue
                                @endif
                                <li>
                                    @if (isset($item['subItems']))
                                        <!-- Menu Item with Submenu -->
                                        <div class="relative" x-data="{ flyoutOpen: false, flyoutY: 0 }">
                                            <!-- Expanded: toggle submenu inline -->
                                            <button x-show="!isCollapsed"
                                                @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }}, '{{ $item['subItems'][0]['path'] }}')"
                                                class="menu-item group w-full"
                                                :class="[
                                                    isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                    'menu-item-active' : 'menu-item-inactive'
                                                ]">

                                                <!-- Icon -->
                                                <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                        'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                    {!! MenuHelper::getIconSvg($item['icon']) !!}
                                                </span>

                                                <!-- Text -->
                                                <span class="menu-item-text flex items-center gap-2">
                                                    {{ $item['name'] }}
                                                    @if (!empty($item['new']))
                                                        <span class="absolute right-10"
                                                            :class="isActive('{{ $item['path'] ?? '' }}') ?
                                                                'menu-dropdown-badge menu-dropdown-badge-active' :
                                                                'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                            new
                                                        </span>
                                                    @endif
                                                </span>

                                                <!-- Chevron Down Icon -->
                                                <svg class="ml-auto w-5 h-5 transition-transform duration-200"
                                                    :class="{
                                                        'rotate-180 text-brand-500': isSubmenuOpen({{ $groupIndex }},
                                                            {{ $itemIndex }})
                                                    }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>

                                            <!-- Collapsed: icon with flyout submenu -->
                                            <button x-show="isCollapsed"
                                                @mouseenter="flyoutY = $el.getBoundingClientRect().top; flyoutOpen = true"
                                                @mouseleave="flyoutOpen = false"
                                                @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }}, '{{ $item['subItems'][0]['path'] }}')"
                                                class="menu-item group w-full xl:justify-center"
                                                :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                    'menu-item-active' : 'menu-item-inactive'">
                                                <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                        'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                    {!! MenuHelper::getIconSvg($item['icon']) !!}
                                                </span>
                                            </button>

                                            <!-- Flyout submenu (shown on hover when collapsed) -->
                                            <div x-show="flyoutOpen"
                                                x-transition:enter="transition ease-out duration-150"
                                                x-transition:enter-start="opacity-0 translate-x-1"
                                                x-transition:enter-end="opacity-100 translate-x-0"
                                                x-transition:leave="transition ease-in duration-100"
                                                x-transition:leave-start="opacity-100 translate-x-0"
                                                x-transition:leave-end="opacity-0 translate-x-1"
                                                @mouseenter="flyoutOpen = true"
                                                @mouseleave="flyoutOpen = false"
                                                class="fixed left-[90px] z-99999 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                                                :style="{ top: flyoutY + 'px' }">
                                                <div class="px-3 py-1.5 text-xs font-semibold uppercase text-gray-400">
                                                    {{ $item['name'] }}
                                                </div>
                                                @foreach ($item['subItems'] as $subItem)
                                                    <a href="{{ $subItem['path'] }}"
                                                        class="flex items-center gap-2 px-3 py-1.5 text-sm transition-colors"
                                                        :class="isActive('{{ $subItem['path'] }}') ?
                                                            'text-brand-500 font-medium' :
                                                            'text-gray-700 dark:text-gray-300 hover:text-brand-500 dark:hover:text-brand-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'">
                                                        {{ $subItem['name'] }}
                                                        @if (!empty($subItem['new']))
                                                            <span class="menu-dropdown-badge menu-dropdown-badge-inactive">new</span>
                                                        @endif
                                                        @if (!empty($subItem['pro']))
                                                            <span class="menu-dropdown-badge-pro menu-dropdown-badge-pro-inactive">pro</span>
                                                        @endif
                                                    </a>
                                                @endforeach
                                            </div>

                                            <!-- Expanded inline submenu -->
                                            <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && !isCollapsed">
                                                <ul class="mt-2 space-y-1 ml-9">
                                                    @foreach ($item['subItems'] as $subItem)
                                                        <li>
                                                            <a href="{{ $subItem['path'] }}" class="menu-dropdown-item"
                                                                :class="isActive('{{ $subItem['path'] }}') ?
                                                                    'menu-dropdown-item-active' :
                                                                    'menu-dropdown-item-inactive'">
                                                                {{ $subItem['name'] }}
                                                                <span class="flex items-center gap-1 ml-auto">
                                                                    @if (!empty($subItem['new']))
                                                                        <span
                                                                            :class="isActive('{{ $subItem['path'] }}') ?
                                                                                'menu-dropdown-badge menu-dropdown-badge-active' :
                                                                                'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                                            new
                                                                        </span>
                                                                    @endif
                                                                    @if (!empty($subItem['pro']))
                                                                        <span
                                                                            :class="isActive('{{ $subItem['path'] }}') ?
                                                                                'menu-dropdown-badge-pro menu-dropdown-badge-pro-active' :
                                                                                'menu-dropdown-badge-pro menu-dropdown-badge-pro-inactive'">
                                                                            pro
                                                                        </span>
                                                                    @endif
                                                                </span>
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Simple Menu Item -->
                                        <div class="relative" x-data="{ tooltipOpen: false, tooltipY: 0 }">
                                            <a href="{{ $item['path'] }}" class="menu-item group"
                                                @mouseenter="if (!$store.sidebar.isExpanded && !$store.sidebar.isMobileOpen) { tooltipY = $el.getBoundingClientRect().top + $el.offsetHeight / 2; tooltipOpen = true; }"
                                                @mouseleave="tooltipOpen = false"
                                                :class="[
                                                    isActive('{{ $item['path'] }}') ? 'menu-item-active' :
                                                    'menu-item-inactive',
                                                    isCollapsed ? 'xl:justify-center' : 'justify-start'
                                                ]">

                                                <!-- Icon -->
                                                <span
                                                    :class="isActive('{{ $item['path'] }}') ? 'menu-item-icon-active' :
                                                        'menu-item-icon-inactive'">
                                                    {!! MenuHelper::getIconSvg($item['icon']) !!}
                                                </span>

                                                <!-- Text (expanded only) -->
                                                <span x-show="!isCollapsed"
                                                    class="menu-item-text flex items-center gap-2">
                                                    {{ $item['name'] }}
                                                    @if (!empty($item['new']))
                                                        <span
                                                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-500 text-white">
                                                            new
                                                        </span>
                                                    @endif
                                                </span>
                                            </a>

                                            <!-- Tooltip (collapsed only) -->
                                            <div x-show="tooltipOpen"
                                                x-transition:enter="transition ease-out duration-150"
                                                x-transition:enter-start="opacity-0 translate-x-1"
                                                x-transition:enter-end="opacity-100 translate-x-0"
                                                x-transition:leave="transition ease-in duration-100"
                                                x-transition:leave-start="opacity-100 translate-x-0"
                                                x-transition:leave-end="opacity-0 translate-x-1"
                                                class="pointer-events-none fixed left-[90px] z-99999 ml-3 -translate-y-1/2"
                                                :style="{ top: tooltipY + 'px' }">
                                                <div class="whitespace-nowrap rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white shadow-lg dark:bg-gray-700">
                                                    {{ $item['name'] }}
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <!-- Sidebar Widget -->
        <div x-data x-show="!isCollapsed" x-transition class="mt-auto">
            @include('layouts.sidebar-widget')
        </div>

    </div>
</aside>

<!-- Mobile Overlay -->
<div x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobileOpen(false)"
    class="fixed z-50 h-screen w-full bg-gray-900/50"></div>
