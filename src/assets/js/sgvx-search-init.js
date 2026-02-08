/**
 * Utility: Fuzzy Search Initialization
 * Uses Fuse.js to provide robust fuzzy searching across the app.
 */

/**
 * Window-level helper to create a Fuse instance for a set of items
 */
window.sgvxCreateFuse = function (itemSelector, keys = ['text']) {
    const itemElements = document.querySelectorAll(itemSelector);
    if (!itemElements.length) {
        console.warn('sgvxCreateFuse: No elements found for selector:', itemSelector);
        return null;
    }

    const items = Array.from(itemElements).map(el => {
        // Search BOTH the specific metadata AND all visible text in the row
        const metaText = el.dataset.search || '';
        const visibleText = el.innerText || el.textContent || '';
        const searchText = (metaText + ' ' + visibleText).toLowerCase().replace(/\s+/g, ' ').trim();

        return { el: el, text: searchText };
    });

    console.log(`sgvxCreateFuse: Indexed ${items.length} items for ${itemSelector}`);

    return new Fuse(items, {
        keys: keys,
        threshold: 0.5,
        ignoreLocation: true,
        distance: 1000,
        minMatchCharLength: 1
    });
};

window.sgvxGetFuzzyMatches = function (fuse, query) {
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
        console.error('sgvxGetFuzzyMatches Error:', e);
        return new Set();
    }
};

window.sgvxInitFuzzySearch = function (inputSelector, containerSelector, itemSelector) {
    const input = document.querySelector(inputSelector);
    if (!input) {
        console.warn('sgvxInitFuzzySearch: Input not found for selector:', inputSelector);
        return;
    }

    console.log(`sgvxInitFuzzySearch: Initialized for input ${inputSelector}, targeting ${itemSelector}`);

    // We refresh the list occasionally to handle dynamic items
    let fuse = null;

    const refreshFuse = () => {
        fuse = window.sgvxCreateFuse(itemSelector);
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
            console.log(`sgvxInitFuzzySearch [${inputSelector}]: Query cleared, showing all ${items.length} items`);
            return;
        }

        // Fuzzy search
        const results = fuse.search(query);
        const matches = new Set(results.filter(r => r && r.item).map(r => r.item.el));

        console.log(`sgvxInitFuzzySearch [${inputSelector}]: Query="${query}", Found ${matches.size}/${items.length} matches`);

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
        sgvxInitFuzzySearch('#facility-list-search', null, '.list-group-item[data-search]');
    }
    if (document.getElementById('bookingSearch')) {
        sgvxInitFuzzySearch('#bookingSearch', null, '.booking-row[data-search]');
    }
    // Modules with specific search logic (Residents, Flats, Vehicles, Staff, Assets, Expenses)
    // are handled in their own JS files to avoid conflicts with tab/filter state.

    // Resident Dashboard Search Initializations
    if (document.getElementById('facility-dashboard-search')) {
        sgvxInitFuzzySearch('#facility-dashboard-search', null, '.facility-card');
    }
    if (document.getElementById('booking-dashboard-search')) {
        sgvxInitFuzzySearch('#booking-dashboard-search', null, '.booking-dash-row');
    }
});
