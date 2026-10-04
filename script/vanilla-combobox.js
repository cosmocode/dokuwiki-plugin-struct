/**
 * A custom web component that transforms a standard HTML select into a combo box,
 * allowing users to both select from a dropdown and search by typing.
 */
/* */
class VanillaCombobox extends HTMLElement {
    #select;
    #input;
    #dropdown;
    #separator;
    #multiple;
    #placeholder;
    #outsideClickListener;

    // region initialization

    /**
     * Creates a new VanillaCombobox instance.
     * Initializes the shadow DOM.
     */
    constructor() {
        super();
        this.attachShadow({mode: 'open'});
    }

    /**
     * Called when the element is connected to the DOM.
     * Initializes the component, sets up the shadow DOM, and binds event listeners.
     * @returns {void}
     */
    connectedCallback() {
        this.#separator = this.getAttribute('separator') || ',';
        this.#placeholder = this.getAttribute('placeholder') || '';
        this.#setupShadowDOM();
        this.#initializeStyles();
        this.#updateInputFromSelect();
        this.#registerEventListeners();
    }

    /**
     * Most event handlers will be garbage collected when the element is removed from the DOM.
     * However, we need to remove the outside click listener attached to the document to prevent memory leaks.
     * @returns {void}
     */
    disconnectedCallback() {
        document.removeEventListener('click', this.#outsideClickListener);
    }

    /**
     * Sets up the shadow DOM with the necessary elements and styles.
     * Creates the input field and dropdown container.
     * @private
     * @returns {void}
     */
    #setupShadowDOM() {
        // Get the select element from the light DOM
        this.#select = this.querySelector('select');
        if (!this.#select) {
            console.error('VanillaCombobox: No select element found');
            return;
        }

        this.#multiple = this.#select.multiple;

        // Create the input field
        this.#input = document.createElement('input');
        this.#input.type = 'text';
        this.#input.autocomplete = 'off';
        this.#input.part = 'input';
        this.#input.placeholder = this.#placeholder;
        this.#input.required = this.#select.required;
        this.#input.disabled = this.#select.disabled;

        // Create the dropdown container
        this.#dropdown = document.createElement('div');
        this.#dropdown.className = 'dropdown';
        this.#dropdown.part = 'dropdown';
        this.#dropdown.style.display = 'none';

        // Add styles to the shadow DOM
        const style = document.createElement('style');
        style.textContent = `
            :host {
                display: inline-block;
                position: relative;
            }
            .dropdown {
                position: absolute;
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid FieldText;
                background-color: Field;
                color: FieldText;
                z-index: 1000;
                width: max-content;
                box-sizing: border-box;
            }
            .option {
                padding: 5px 10px;
                cursor: pointer;
            }
            .option:hover, .option.selected {
                background-color: Highlight;
                color: HighlightText;
            }
        `;

        // Append elements to the shadow DOM
        this.shadowRoot.appendChild(style);

        this.shadowRoot.appendChild(this.#input);
        this.shadowRoot.appendChild(this.#dropdown);
    }

    /**
     * Initializes the styles for the combobox components.
     * Copies styles from browser defaults and applies them to the custom elements.
     * @private
     * @returns {void}
     */
    #initializeStyles() {
        // create a temporary input element to copy styles from
        const input = document.createElement('input');
        this.parentElement.insertBefore(input, this);
        const defaultStyles = window.getComputedStyle(input);

        // browser default styles
        const inputStyles = window.getComputedStyle(this.#input);
        const dropdownStyles = window.getComputedStyle(this.#dropdown);

        // copy styles from the temporary input to the input element
        for (const property of defaultStyles) {
            const newValue = defaultStyles.getPropertyValue(property);
            const oldValue = inputStyles.getPropertyValue(property);
            if (newValue === oldValue) continue;
            this.#input.style.setProperty(property, newValue);
        }
        this.#input.style.outline = 'none';

        // copy select styles to the dropdown
        for (const property of defaultStyles) {
            const newValue = defaultStyles.getPropertyValue(property);
            const oldValue = dropdownStyles.getPropertyValue(property);
            if (newValue === oldValue) continue;
            if (!property.match(/^(border|color|background|font|padding)/)) continue;
            this.#dropdown.style.setProperty(property, newValue);
        }
        this.#dropdown.style.minWidth = `${this.#input.offsetWidth}px`;
        this.#dropdown.style.borderTop = 'none';

        // remove the temporary input element
        this.parentElement.removeChild(input);
    }

    // endregion
    // region Event Handling

    /**
     * Registers all event listeners for the combobox.
     * Sets up input, focus, blur, and keyboard events.
     * @private
     * @returns {void}
     */
    #registerEventListeners() {
        this.#input.addEventListener('focus', this.#onFocus.bind(this));
        // Delay to allow click event on dropdown
        this.#input.addEventListener('blur', () => setTimeout(() => this.#onBlur(), 150));
        this.#input.addEventListener('input', this.#onInput.bind(this));
        this.#input.addEventListener('keydown', this.#onKeyDown.bind(this));

        this.#outsideClickListener = (event) => {
            if (this.contains(event.target) || this.shadowRoot.contains(event.target)) return;
            this.#closeDropdown();
        };
        document.addEventListener('click', this.#outsideClickListener);
    }


    /**
     * Handles the focus event on the input field.
     * Updates the values and appends the separator if multiple selection is enabled.
     * Shows the dropdown with available options.
     * @private
     * @returns {void}
     */
    #onFocus() {
        this.#updateInputFromSelect();
        this.#showDropdown();
    }

    /**
     * Handles the blur event on the input field.
     * Closes the dropdown and updates the input field (removes the separator).
     * Synchronizes the select element with the input value.
     * @private
     * @returns {void}
     */
    #onBlur() {
        this.#closeDropdown();
        this.#updateSelectFromInput();
        this.#updateInputFromSelect();
    }

    /**
     * Handles the input event on the input field.
     * Shows the dropdown with filtered options based on the current input.
     * @private
     * @returns {void}
     */
    #onInput() {
        this.#showDropdown();
    }

    /**
     * Handles keyboard navigation in the dropdown.
     * Supports arrow keys, Enter, and Escape.
     * @private
     * @param {KeyboardEvent} event - The keyboard event
     * @returns {void}
     */
    #onKeyDown(event) {
        // Only handle keyboard navigation if dropdown is visible
        if (this.#dropdown.style.display !== 'block') return;

        const items = this.#dropdown.querySelectorAll('.option');
        const selectedItem = this.#dropdown.querySelector('.option.selected');
        let selectedIndex = Array.from(items).indexOf(selectedItem);

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                this.#highlightItem(items, selectedIndex);
                break;

            case 'ArrowUp':
                event.preventDefault();
                selectedIndex = selectedIndex > 0 ? selectedIndex - 1 : items.length - 1;
                this.#highlightItem(items, selectedIndex);
                break;

            case 'Enter':
                event.preventDefault();
                if (selectedItem) {
                    selectedItem.click();
                }
                break;

            case 'Escape':
                event.preventDefault();
                this.#dropdown.style.display = 'none';
                break;
        }
    }

    // endregion
    // region Data Handling

    /**
     * Updates the input field value based on the selected options in the select element.
     * Joins multiple selections with the separator if multiple selection is enabled.
     * @private
     * @returns {void}
     */
    #updateInputFromSelect() {
        const selectedOptions = Array.from(this.#select.selectedOptions);

        if (selectedOptions.length > 0) {
            this.#input.value = selectedOptions
                .map(option => option.textContent)
                .join(`${this.#separator} `);
        } else {
            this.#input.value = '';
        }

        // If the input is focused and multiple selection is enabled, append the separator
        if ((this.shadowRoot.activeElement === this.#input) && this.#multiple && this.#input.value !== '') {
            this.#input.value += this.#separator + ' ';
        }
    }

    /**
     * Gets all selected options and unselects those whose text is no longer in the input.
     * Synchronizes the select element with the input field content.
     * @private
     * @returns {void}
     */
    #updateSelectFromInput() {
        const selectedOptions = Array.from(this.#select.selectedOptions);
        let inputTexts = [this.#input.value];
        if (this.#multiple) {
            inputTexts = this.#input.value.split(this.#separator).map(text => text.trim());
        }

        selectedOptions.forEach(option => {
            if (!inputTexts.includes(option.textContent)) {
                option.selected = false;
            }
        })
    }

    // endregion
    // region Dropdown Handling

    /**
     * Shows the dropdown with options filtered by the current input value.
     * Creates option elements for each matching option.
     * @private
     * @returns {void}
     */
    #showDropdown() {
        // get the currently edited value
        let query = this.#input.value.trim();
        if (this.#multiple) {
            query = query.split(this.#separator).pop().trim()
        }

        // Filter the options matching the input value
        const options = Array.from(this.#select.options);
        const filteredOptions = options.filter(
            option => option.textContent.toLowerCase().includes(query.toLowerCase())
        );
        if (filteredOptions.length === 0) {
            this.#closeDropdown();
            return;
        }

        // Create the dropdown items
        this.#dropdown.innerHTML = '';
        filteredOptions.forEach(option => {
            if (this.#multiple && option.value === '') return; // Ignore empty options in multiple mode

            const div = document.createElement('div');
            div.textContent = option.textContent;
            div.className = 'option';
            div.part = 'option';
            this.#dropdown.appendChild(div);

            // Add click event to each option
            div.addEventListener('click', () => this.#selectOption(option));
        });

        // Show the dropdown
        this.#dropdown.style.display = 'block';
    }

    /**
     * Closes the dropdown by hiding it.
     * @private
     * @returns {void}
     */
    #closeDropdown() {
        this.#dropdown.style.display = 'none';
    }

    /**
     * Highlights a specific item in the dropdown.
     * Removes selection from all items and adds it to the specified one.
     * @private
     * @param {NodeListOf<Element>} items - The dropdown items
     * @param {number} index - The index of the item to highlight
     * @returns {void}
     */
    #highlightItem(items, index) {
        // Remove selection from all items
        items.forEach(item => item.classList.remove('selected'));

        // Add selection to current item
        if (items[index]) {
            items[index].classList.add('selected');
            // Ensure the selected item is visible in the dropdown
            items[index].scrollIntoView({block: 'nearest'});
        }
    }

    /**
     * Selects an option from the dropdown.
     * Updates the select element and input field, then closes the dropdown.
     * @private
     * @param {HTMLOptionElement} option - The option to select
     * @returns {void}
     */
    #selectOption(option) {
        option.selected = true;
        this.#updateInputFromSelect();
        this.#closeDropdown();
        this.#input.focus();
    }

    // endregion
}

// Register the custom element
customElements.define('vanilla-combobox', VanillaCombobox);
