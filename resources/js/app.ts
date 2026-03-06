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

window.Alpine = Alpine;

Alpine.start();
