/**
 * Autocomplete for Diabetes Management Project
 * Supports medication_id tracking for structured data saving.
 * Usage: enableAutocomplete(inputElement, apiEndpoint, onSelectCallback)
 */

function enableAutocomplete(inputElement, apiEndpoint, onSelect) {
    let currentFocus = -1;
    let wrapper;

    // Create wrapper once
    if (!inputElement.parentNode.classList.contains('autocomplete-wrapper')) {
        wrapper = document.createElement("div");
        wrapper.className = 'autocomplete-wrapper';
        wrapper.style.position = "relative";
        inputElement.parentNode.insertBefore(wrapper, inputElement);
        wrapper.appendChild(inputElement);
    } else {
        wrapper = inputElement.parentNode;
    }

    function fetchAndShow(val) {
        closeAllLists();
        currentFocus = -1;

        const listDiv = document.createElement("DIV");
        listDiv.setAttribute("id", inputElement.id + "autocomplete-list");
        listDiv.setAttribute("class", "autocomplete-items");
        // Style the list
        listDiv.style.position = "absolute";
        listDiv.style.borderColor = "var(--border, #d4d4d4)";
        listDiv.style.borderStyle = "solid";
        listDiv.style.borderWidth = "1px";
        listDiv.style.zIndex = "99";
        listDiv.style.top = "100%";
        listDiv.style.left = "0";
        listDiv.style.right = "0";
        listDiv.style.backgroundColor = "var(--surface, #fff)";
        listDiv.style.maxHeight = "250px";
        listDiv.style.overflowY = "auto";
        listDiv.style.boxShadow = "var(--shadow-medium, 0 4px 6px rgba(0,0,0,0.1))";
        listDiv.style.borderRadius = "0 0 8px 8px";

        wrapper.appendChild(listDiv);

        // Fetch data
        const url = apiEndpoint + (val ? "?q=" + encodeURIComponent(val) : "");

        fetch(url)
            .then(response => response.json())
            .then(data => {
                listDiv.innerHTML = "";
                if (data.length === 0) {
                    const noResult = document.createElement("div");
                    noResult.innerHTML = "لا توجد نتائج";
                    noResult.style.padding = "10px";
                    noResult.style.color = "var(--muted, #666)";
                    listDiv.appendChild(noResult);
                    return;
                }

                data.forEach(item => {
                    const itemDiv = document.createElement("DIV");
                    const name = item.name_ar + " | " + item.name_en;
                    const suitability = item.suitability ? `<small style='display:block; color:var(--primary, #10b981); font-size:0.8em;'>${item.suitability}</small>` : "";

                    let content = "";
                    if (val) {
                        // Highlight match
                        const escaped = val.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        const regex = new RegExp(escaped, "gi");
                        content = name.replace(regex, (match) => `<strong>${match}</strong>`);
                    } else {
                        content = name;
                    }

                    itemDiv.innerHTML = `<div>${content}</div>${suitability}`;
                    // Store the full item data for retrieval
                    itemDiv.dataset.itemId = item.id || '';
                    itemDiv.dataset.nameAr = item.name_ar || '';
                    itemDiv.dataset.nameEn = item.name_en || '';

                    itemDiv.style.padding = "10px";
                    itemDiv.style.cursor = "pointer";
                    itemDiv.style.borderBottom = "1px solid var(--border, #eee)";
                    itemDiv.style.color = "var(--ink, #333)";

                    itemDiv.addEventListener("click", function (e) {
                        inputElement.value = this.dataset.nameAr;
                        if (onSelect) onSelect(item);
                        closeAllLists();
                    });
                    listDiv.appendChild(itemDiv);
                });
            })
            .catch(err => console.error("Autocomplete Error:", err));
    }

    inputElement.addEventListener("input", function (e) {
        fetchAndShow(this.value);
    });

    inputElement.addEventListener("focus", function (e) {
        if (!document.getElementById(this.id + "autocomplete-list")) {
            fetchAndShow(this.value);
        }
    });

    inputElement.addEventListener("click", function (e) {
        if (!document.getElementById(this.id + "autocomplete-list")) {
            fetchAndShow(this.value);
        }
    });

    inputElement.addEventListener("keydown", function (e) {
        let x = document.getElementById(this.id + "autocomplete-list");
        if (x) x = x.getElementsByTagName("div");
        if (e.keyCode == 40) { // Down
            currentFocus++;
            addActive(x);
        } else if (e.keyCode == 38) { // Up
            currentFocus--;
            addActive(x);
        } else if (e.keyCode == 13) { // Enter
            e.preventDefault();
            if (currentFocus > -1) {
                if (x) x[currentFocus].click();
            }
        }
    });

    function addActive(x) {
        if (!x) return false;
        removeActive(x);
        if (currentFocus >= x.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = (x.length - 1);
        x[currentFocus].style.backgroundColor = "var(--surface-2, #e9e9e9)";
    }

    function removeActive(x) {
        for (let i = 0; i < x.length; i++) {
            x[i].style.backgroundColor = "var(--surface, #fff)";
        }
    }

    function closeAllLists(elmnt) {
        const x = document.getElementsByClassName("autocomplete-items");
        for (let i = 0; i < x.length; i++) {
            if (elmnt != x[i] && elmnt != inputElement) {
                x[i].parentNode.removeChild(x[i]);
            }
        }
    }

    document.addEventListener("click", function (e) {
        closeAllLists(e.target);
    });
}
