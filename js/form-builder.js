/**
 * form-builder.js
 * Handles dynamic field and section management in the Form Builder.
 */

// Global counter for all row indices (fields, sections, section ends).
// Starts at 1 because index 0 is the default field already in the HTML.
let nextIndex = 1;

// DOM references
const container = document.getElementById("fields-container");
const addFieldBtn = document.getElementById("add-field-btn");
const addSectionBtn = document.getElementById("add-section-btn");
const noFieldsMsg = document.getElementById("no-fields-msg");

// Row creators

function createFieldRow(index) {
    const row = document.createElement("div");
    row.className = "field-row";
    row.dataset.type = "field";
    row.dataset.index = index;

    row.innerHTML = `
        <div class="field-row-main">
            <input type="text"
                   name="fields[${index}][label]"
                   class="form-control field-label"
                   placeholder="e.g. First Name">
            <input type="text"
                   name="fields[${index}][db_name]"
                   class="form-control field-db-name"
                   placeholder="auto">
            <select name="fields[${index}][type]" class="form-control field-type">
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="datetime">Date &amp; Time</option>
                <option value="textarea">Textarea</option>
                <option value="email">Email</option>
                <option value="phone">Phone</option>
            </select>
            <div class="builder-checkbox-cell">
                <input type="checkbox" name="fields[${index}][required]" value="1">
            </div>
            <button type="button" class="btn btn-danger btn-sm remove-field-btn">Remove</button>
        </div>
    `;
    return row;
}

function createSectionRow(index) {
    const row = document.createElement("div");
    row.className = "section-row";
    row.dataset.type = "section";
    row.dataset.index = index;
    row.dataset.locked = "false";

    row.innerHTML = `
        <input type="hidden" name="fields[${index}][type]" value="section_header">
        <span class="section-row-icon">§</span>
        <input type="text"
               name="fields[${index}][label]"
               class="form-control section-label-input"
               placeholder="Section heading, e.g. Patient Details">
        <button type="button" class="btn btn-sm btn-outline section-lock-btn">Lock</button>
        <button type="button" class="btn btn-sm btn-danger remove-section-btn">Remove</button>
    `;
    return row;
}

function createSectionEndRow(index, sectionIndex) {
    const row = document.createElement("div");
    row.className = "section-end-row";
    row.dataset.type = "section_end";
    row.dataset.index = index;
    row.dataset.sectionIndex = sectionIndex;

    row.innerHTML = `
        <input type="hidden" name="fields[${index}][type]" value="section_end">
        <span>&#9135; Section closed - fields added below will be outside this section</span>
    `;
    return row;
}

// Lock / Unlock

function lockSection(sectionRow) {
    const sectionIndex = sectionRow.dataset.index;

    // Find where to insert the end marker:
    // immediately before the next section or existing lock, or at the container end.
    let insertBefore = null;
    let node = sectionRow.nextElementSibling;
    while (node) {
        if (
            node.dataset.type === "section" ||
            node.dataset.type === "section_end"
        ) {
            insertBefore = node;
            break;
        }
        node = node.nextElementSibling;
    }

    const endRow = createSectionEndRow(nextIndex++, sectionIndex);
    if (insertBefore) {
        container.insertBefore(endRow, insertBefore);
    } else {
        container.appendChild(endRow);
    }

    sectionRow.dataset.locked = "true";
    const lockBtn = sectionRow.querySelector(".section-lock-btn");
    lockBtn.textContent = "Unlock";
    lockBtn.classList.replace("btn-outline", "btn-warning");
}

function unlockSection(sectionRow) {
    const sectionIndex = sectionRow.dataset.index;

    // Remove the associated section_end row
    const endRow = container.querySelector(
        `.section-end-row[data-section-index="${sectionIndex}"]`,
    );
    if (endRow) endRow.remove();

    sectionRow.dataset.locked = "false";
    const lockBtn = sectionRow.querySelector(".section-lock-btn");
    lockBtn.textContent = "Lock";
    lockBtn.classList.replace("btn-warning", "btn-outline");
}

function removeSection(sectionRow) {
    // If locked, also clean up its end marker
    if (sectionRow.dataset.locked === "true") {
        unlockSection(sectionRow);
    }
    sectionRow.remove();
    updateUI();
}

// UI state

function updateUI() {
    const fieldRows = container.querySelectorAll(".field-row");

    // Hide Remove on the last remaining real field row
    fieldRows.forEach((row) => {
        const btn = row.querySelector(".remove-field-btn");
        if (btn)
            btn.style.visibility =
                fieldRows.length === 1 ? "hidden" : "visible";
    });

    // Show empty message only when the container has nothing at all
    noFieldsMsg.style.display =
        container.children.length === 0 ? "block" : "none";
}

// Auto-generate db_name from label

function labelToDbName(label) {
    return label
        .toLowerCase()
        .trim()
        .replace(/\s+/g, "_")
        .replace(/[^a-z0-9_]/g, "")
        .replace(/^[0-9]/, "_$&");
}

function attachLabelListener(row) {
    const labelInput = row.querySelector(".field-label");
    const dbNameInput = row.querySelector(".field-db-name");
    if (!labelInput || !dbNameInput) return;

    labelInput.addEventListener("input", () => {
        if (!dbNameInput.dataset.manuallyEdited) {
            dbNameInput.value = labelToDbName(labelInput.value);
        }
    });

    dbNameInput.addEventListener("input", () => {
        dbNameInput.dataset.manuallyEdited = "true";
    });

    dbNameInput.addEventListener("change", () => {
        if (dbNameInput.value === "") delete dbNameInput.dataset.manuallyEdited;
    });
}

// Button events

addFieldBtn.addEventListener("click", () => {
    const row = createFieldRow(nextIndex++);
    container.appendChild(row);
    attachLabelListener(row);
    updateUI();
    row.querySelector(".field-label").focus();
});

addSectionBtn.addEventListener("click", () => {
    const row = createSectionRow(nextIndex++);
    container.appendChild(row);
    updateUI();
    row.querySelector(".section-label-input").focus();
});

// Delegated handler covers remove-field, lock/unlock, remove-section
container.addEventListener("click", (e) => {
    if (e.target.classList.contains("remove-field-btn")) {
        e.target.closest(".field-row").remove();
        updateUI();
        return;
    }

    if (e.target.classList.contains("section-lock-btn")) {
        const sectionRow = e.target.closest(".section-row");
        if (sectionRow.dataset.locked === "true") {
            unlockSection(sectionRow);
        } else {
            lockSection(sectionRow);
        }
        return;
    }

    if (e.target.classList.contains("remove-section-btn")) {
        removeSection(e.target.closest(".section-row"));
    }
});

// Initialise
attachLabelListener(container.querySelector('.field-row[data-index="0"]'));
updateUI();
