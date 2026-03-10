import './bootstrap';
import Alpine from 'alpinejs';

import { autoSaveField } from './components/auto-save-field';
import { autoSaveForm } from './components/auto-save-form';
import { sortableList } from './components/sortable-list';
import { sortableKanban } from './components/sortable-kanban';
import { filterManager } from './components/filter-manager';
import { markdownEditor } from './components/markdown-editor';
import { privacyToggle } from './components/privacy-toggle';
import { confirmDialog } from './components/confirm-dialog';
import { keyboardShortcuts } from './components/keyboard-shortcuts';
import { analyticsChart } from './components/analytics-chart';
import { analyticsBoard } from './components/analytics-board';
import { weeklyChart } from './components/weekly-chart';
import { widgetConfigurator } from './components/widget-configurator';
import { agreementManager } from './components/agreement-manager';
import { inlineSelect } from './components/inline-select';
import { liveCounter } from './components/live-counter';

declare global {
    interface Window {
        Alpine: typeof Alpine;
    }
}

Alpine.data('autoSaveField', autoSaveField as Parameters<typeof Alpine.data>[1]);
Alpine.data('autoSaveForm', autoSaveForm as Parameters<typeof Alpine.data>[1]);
Alpine.data('sortableList', sortableList as Parameters<typeof Alpine.data>[1]);
Alpine.data('sortableKanban', sortableKanban as Parameters<typeof Alpine.data>[1]);
Alpine.data('filterManager', filterManager as Parameters<typeof Alpine.data>[1]);
Alpine.data('markdownEditor', markdownEditor as Parameters<typeof Alpine.data>[1]);
Alpine.data('privacyToggle', privacyToggle as Parameters<typeof Alpine.data>[1]);
Alpine.data('confirmDialog', confirmDialog as Parameters<typeof Alpine.data>[1]);
Alpine.data('keyboardShortcuts', keyboardShortcuts as Parameters<typeof Alpine.data>[1]);
Alpine.data('analyticsChart', analyticsChart as Parameters<typeof Alpine.data>[1]);
Alpine.data('analyticsBoard', analyticsBoard as Parameters<typeof Alpine.data>[1]);
Alpine.data('weeklyChart', weeklyChart as Parameters<typeof Alpine.data>[1]);
Alpine.data('widgetConfigurator', widgetConfigurator as Parameters<typeof Alpine.data>[1]);
Alpine.data('agreementManager', agreementManager as Parameters<typeof Alpine.data>[1]);
Alpine.data('inlineSelect', inlineSelect as Parameters<typeof Alpine.data>[1]);
Alpine.data('liveCounter', liveCounter as Parameters<typeof Alpine.data>[1]);

Alpine.store('taskList', { showCompleted: false });

window.Alpine = Alpine;

Alpine.start();
