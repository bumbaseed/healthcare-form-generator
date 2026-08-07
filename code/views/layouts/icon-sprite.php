<?php
/**
 * Inline SVG icon sprite (Lucide icons) used in the project.
 *
 * Included once per page from header.php, right inside <body>. Every icon is defined as a <symbol> in a hidden <svg>, so any subsequent <use href="#icon"> on the page (rendered by the icon() helper) resolves to a symbol that's already in the DOM. One HTTP request covers the whole app, no external sprite file is needed.
 *
 * 
 *
 */
?>
<svg xmlns="http://www.w3.org/2000/svg" class="icon-sprite" aria-hidden="true">
    <defs>
        <!--  Navigation / layout  -->
        <symbol id="icon-home" viewBox="0 0 24 24">
            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
        </symbol>
        <symbol id="icon-dashboard" viewBox="0 0 24 24">
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
        </symbol>
        <symbol id="icon-clipboard-list" viewBox="0 0 24 24">
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <path d="M12 11h4" />
            <path d="M12 16h4" />
            <path d="M8 11h.01" />
            <path d="M8 16h.01" />
        </symbol>
        <symbol id="icon-folder" viewBox="0 0 24 24">
            <path
                d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
        </symbol>
        <symbol id="icon-files" viewBox="0 0 24 24">
            <path d="M20 7h-3a2 2 0 0 1-2-2V2" />
            <path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z" />
            <path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8" />
        </symbol>
        <symbol id="icon-user" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="5" />
            <path d="M20 21a8 8 0 0 0-16 0" />
        </symbol>
        <symbol id="icon-users" viewBox="0 0 24 24">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </symbol>
        <symbol id="icon-switch" viewBox="0 0 24 24">
            <path d="m16 3 4 4-4 4" />
            <path d="M20 7H4" />
            <path d="m8 21-4-4 4-4" />
            <path d="M4 17h16" />
        </symbol>
        <symbol id="icon-log-out" viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" x2="9" y1="12" y2="12" />
        </symbol>
        <symbol id="icon-settings" viewBox="0 0 24 24">
            <path
                d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
            <circle cx="12" cy="12" r="3" />
        </symbol>
        <symbol id="icon-plus-circle" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <path d="M8 12h8" />
            <path d="M12 8v8" />
        </symbol>

        <!--  Actions  -->
        <symbol id="icon-edit" viewBox="0 0 24 24">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </symbol>
        <symbol id="icon-trash" viewBox="0 0 24 24">
            <path d="M3 6h18" />
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
            <line x1="10" x2="10" y1="11" y2="17" />
            <line x1="14" x2="14" y1="11" y2="17" />
        </symbol>
        <symbol id="icon-save" viewBox="0 0 24 24">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
            <polyline points="17 21 17 13 7 13 7 21" />
            <polyline points="7 3 7 8 15 8" />
        </symbol>
        <symbol id="icon-download" viewBox="0 0 24 24">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" x2="12" y1="15" y2="3" />
        </symbol>
        <symbol id="icon-file-down" viewBox="0 0 24 24">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
            <path d="M12 18v-6" />
            <path d="m9 15 3 3 3-3" />
        </symbol>
        <symbol id="icon-eye" viewBox="0 0 24 24">
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="3" />
        </symbol>
        <symbol id="icon-check" viewBox="0 0 24 24">
            <path d="M20 6 9 17l-5-5" />
        </symbol>
        <symbol id="icon-x" viewBox="0 0 24 24">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24">
            <path d="M5 12h14" />
            <path d="M12 5v14" />
        </symbol>
        <symbol id="icon-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
        </symbol>
        <symbol id="icon-printer" viewBox="0 0 24 24">
            <path d="M6 9V2h12v7" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect width="12" height="8" x="6" y="14" />
        </symbol>
        <symbol id="icon-arrow-left" viewBox="0 0 24 24">
            <path d="m12 19-7-7 7-7" />
            <path d="M19 12H5" />
        </symbol>
        <symbol id="icon-arrow-right" viewBox="0 0 24 24">
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
        </symbol>
        <symbol id="icon-chevron-right" viewBox="0 0 24 24">
            <path d="m9 18 6-6-6-6" />
        </symbol>
        <symbol id="icon-chevron-down" viewBox="0 0 24 24">
            <path d="m6 9 6 6 6-6" />
        </symbol>
        <symbol id="icon-copy" viewBox="0 0 24 24">
            <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
            <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
        </symbol>
        <symbol id="icon-refresh" viewBox="0 0 24 24">
            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
            <path d="M21 3v5h-5" />
            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
            <path d="M3 21v-5h5" />
        </symbol>

        <!--  Healthcare  -->
        <symbol id="icon-activity" viewBox="0 0 24 24">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </symbol>
        <symbol id="icon-heart" viewBox="0 0 24 24">
            <path
                d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
        </symbol>
        <symbol id="icon-heart-pulse" viewBox="0 0 24 24">
            <path
                d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
            <path d="M3.22 12H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27" />
        </symbol>
        <symbol id="icon-stethoscope" viewBox="0 0 24 24">
            <path d="M11 2v2" />
            <path d="M5 2v2" />
            <path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1" />
            <path d="M8 15a6 6 0 0 0 12 0v-3" />
            <circle cx="20" cy="10" r="2" />
        </symbol>
        <symbol id="icon-pill" viewBox="0 0 24 24">
            <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z" />
            <path d="m8.5 8.5 7 7" />
        </symbol>
        <symbol id="icon-syringe" viewBox="0 0 24 24">
            <path d="m18 2 4 4" />
            <path d="m17 7 3-3" />
            <path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5" />
            <path d="m9 11 4 4" />
            <path d="m5 19-3 3" />
            <path d="m14 4 6 6" />
        </symbol>
        <symbol id="icon-thermometer" viewBox="0 0 24 24">
            <path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z" />
        </symbol>
        <symbol id="icon-hospital" viewBox="0 0 24 24">
            <path d="M12 6v4" />
            <path d="M14 14h-4" />
            <path d="M14 18h-4" />
            <path d="M14 8h-4" />
            <path d="M18 12h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2h2" />
            <path d="M18 22V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v18" />
        </symbol>
        <symbol id="icon-calendar" viewBox="0 0 24 24">
            <path d="M8 2v4" />
            <path d="M16 2v4" />
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M3 10h18" />
        </symbol>
        <symbol id="icon-shield-check" viewBox="0 0 24 24">
            <path
                d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
            <path d="m9 12 2 2 4-4" />
        </symbol>
        <symbol id="icon-file-text" viewBox="0 0 24 24">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
            <path d="M10 9H8" />
            <path d="M16 13H8" />
            <path d="M16 17H8" />
        </symbol>
        <symbol id="icon-clipboard-check" viewBox="0 0 24 24">
            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <path d="m9 14 2 2 4-4" />
        </symbol>

        <!--  Status / alerts  -->
        <symbol id="icon-check-circle" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <path d="m9 12 2 2 4-4" />
        </symbol>
        <symbol id="icon-alert-circle" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" x2="12" y1="8" y2="12" />
            <line x1="12" x2="12.01" y1="16" y2="16" />
        </symbol>
        <symbol id="icon-alert-triangle" viewBox="0 0 24 24">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
            <line x1="12" x2="12" y1="9" y2="13" />
            <line x1="12" x2="12.01" y1="17" y2="17" />
        </symbol>
        <symbol id="icon-info" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 16v-4" />
            <path d="M12 8h.01" />
        </symbol>
    </defs>
</svg>