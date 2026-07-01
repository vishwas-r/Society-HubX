/**
 * Utility: Fuzzy Search Initialization
 * Uses Fuse.js to provide robust fuzzy searching across the app.
 */

/**
 * Window-level helper to create a Fuse instance for a set of items
 */
window.SHUBXCreateFuse = function (itemSelector, optionsOrKeys = ['text']) {
    const itemElements = document.querySelectorAll(itemSelector);
    if (!itemElements.length) {
        // console.warn('SHUBXCreateFuse: No elements found for selector:', itemSelector);
        return null; // Silent fail in production often preferred, logging only if debug
    }

    // Determine config
    let keys = ['text'];
    let threshold = 0.4;
    let searchOnlyMeta = false;

    if (Array.isArray(optionsOrKeys)) {
        keys = optionsOrKeys;
    } else if (typeof optionsOrKeys === 'object') {
        if (optionsOrKeys.keys) keys = optionsOrKeys.keys;
        if (typeof optionsOrKeys.threshold !== 'undefined') threshold = optionsOrKeys.threshold;
        if (optionsOrKeys.searchOnlyMeta) searchOnlyMeta = true;
    }

    const items = Array.from(itemElements).map(el => {
        // Search BOTH the specific metadata AND all visible text in the row by default
        const metaText = el.dataset.search || '';
        let searchText = metaText;

        if (!searchOnlyMeta) {
            const visibleText = el.innerText || el.textContent || '';
            searchText = (metaText + ' ' + visibleText);
        }

        searchText = searchText.toLowerCase().replace(/\s+/g, ' ').trim();

        return { el: el, text: searchText };
    });

    // console.log(`SHUBXCreateFuse: Indexed ${items.length} items for ${itemSelector}`);

    return new Fuse(items, {
        keys: keys,
        threshold: threshold,
        ignoreLocation: true,
        distance: 1000,
        minMatchCharLength: 2,
        useExtendedSearch: true
    });
};

window.SHUBXGetFuzzyMatches = function (fuse, query) {
    if (!query || !fuse) return null;
    try {
        const results = fuse.search(query);
        // Fuse.js returns results as [{ item: { el, text }, refIndex: ... }]
        // but some older versions or configs might return items directly.
        const mapped = results.map(r => {
            if (r.item && r.item.el) return r.item.el;
            if (r.el) return r.el;
            return null;
        }).filter(el => el !== null);

        return new Set(mapped);
    } catch (e) {
        console.error('SHUBXGetFuzzyMatches Error:', e);
        return new Set();
    }
};

window.SHUBXInitFuzzySearch = function (inputSelector, containerSelector, itemSelector) {
    const input = document.querySelector(inputSelector);
    if (!input) {
        console.warn('SHUBXInitFuzzySearch: Input not found for selector:', inputSelector);
        return;
    }

    console.log(`SHUBXInitFuzzySearch: Initialized for input ${inputSelector}, targeting ${itemSelector}`);

    // We refresh the list occasionally to handle dynamic items
    let fuse = null;

    const refreshFuse = () => {
        fuse = window.SHUBXCreateFuse(itemSelector);
    };

    // Initial load
    refreshFuse();

    input.addEventListener('input', function () {
        const query = this.value.trim();
        const items = Array.from(document.querySelectorAll(itemSelector));

        if (!query) {
            // Show all items when query is empty
            items.forEach(el => {
                el.classList.remove('d-none');
                el.style.display = '';
            });
            console.log(`SHUBXInitFuzzySearch [${inputSelector}]: Query cleared, showing all ${items.length} items`);
            return;
        }

        // Fuzzy search
        const results = fuse.search(query);
        const matches = new Set(results.filter(r => r && r.item).map(r => r.item.el));

        console.log(`SHUBXInitFuzzySearch [${inputSelector}]: Query="${query}", Found ${matches.size}/${items.length} matches`);

        items.forEach(el => {
            if (matches.has(el)) {
                el.classList.remove('d-none');
                el.style.display = '';
            } else {
                el.classList.add('d-none');
            }
        });
    });

    // Re-index on focus in case content changed (simple way to stay synced)
    input.addEventListener('focus', refreshFuse);
};

document.addEventListener('DOMContentLoaded', function () {
    // Admin Panel Search Initializations
    if (document.getElementById('facility-list-search')) {
        SHUBXInitFuzzySearch('#facility-list-search', null, '.list-group-item[data-search]');
    }
    if (document.getElementById('bookingSearch')) {
        SHUBXInitFuzzySearch('#bookingSearch', null, '.booking-row[data-search]');
    }
    // Modules with specific search logic (Residents, Flats, Vehicles, Staff, Assets, Expenses)
    // are handled in their own JS files to avoid conflicts with tab/filter state.

    // Resident Dashboard Search Initializations
    if (document.getElementById('facility-dashboard-search')) {
        SHUBXInitFuzzySearch('#facility-dashboard-search', null, '.facility-card');
    }
    if (document.getElementById('booking-dashboard-search')) {
        SHUBXInitFuzzySearch('#booking-dashboard-search', null, '.booking-dash-row');
    }
});
