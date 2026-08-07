<?php
/**
 * Renders form HTML from a form definition. Used by the form builder for the preview pane and by the generated form files at completion time.
 */

require_once __DIR__ . '/functions.php';

class FormRenderer
{
    private $formData = [];

    /**
     * Per-field validation errors keyed by field_name. Populated through renderForm() and consumed by renderField() to mark inputs invalid and append a linked .form-error message.
     */
    private $fieldErrors = [];

    public function renderForm(array $formDefinition, array $existingData = [], bool $readonly = false, array $fieldErrors = [])
    {
        $this->formData = $existingData;
        $this->fieldErrors = $fieldErrors;

        if (empty($formDefinition['fields'])) {
            return '<p class="text-muted">No fields defined for this form.</p>';
        }

        // Group consecutive fields by their section label. Fields with no section share a single anonymous group keyed by null.
        $groups = [];
        foreach ($formDefinition['fields'] as $field) {
            $layout = $this->parseLayout($field);
            $sectionLabel = $layout['section'];

            if (empty($groups) || end($groups)['label'] !== $sectionLabel) {
                $groups[] = ['label' => $sectionLabel, 'fields' => []];
            }
            $groups[count($groups) - 1]['fields'][] = $field;
        }

        $html = '';
        foreach ($groups as $group) {
            $hasSection = $group['label'] !== null;

            if ($hasSection) {
                $html .= '<div class="form-section">';
                $html .= '<div class="form-section-heading">' . escape($group['label']) . '</div>';
            }

            $gridClass = $hasSection ? 'form-field-grid form-field-grid--sectioned' : 'form-field-grid';
            $html .= '<div class="' . $gridClass . '">';
            foreach ($group['fields'] as $field) {
                $html .= $this->renderField($field, $readonly);
            }
            $html .= '</div>';

            if ($hasSection) {
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Pull the section label out of field_options JSON. Underscore-prefixed keys are layout metadata, not real option values.
     */
    private function parseLayout(array $field): array
    {
        $section = null;

        if (!empty($field['field_options'])) {
            $opts = json_decode($field['field_options'], true);
            if (is_array($opts)) {
                $s = $opts['_section'] ?? null;
                $section = ($s !== null && $s !== '') ? $s : null;
            }
        }

        return ['section' => $section];
    }

    public function renderField(array $field, bool $readonly = false)
    {
        $fieldName = $field['field_name'];
        $fieldLabel = $field['field_label'];
        $fieldType = $field['field_type'];
        $isRequired = $field['is_required'] ?? false;
        $helpText = $field['help_text'] ?? null;
        $defaultValue = $field['default_value'] ?? null;
        $options = $field['field_options'] ? json_decode($field['field_options'], true) : [];

        $value = $this->formData[$fieldName] ?? $defaultValue ?? '';

        $hasError = isset($this->fieldErrors[$fieldName]);
        $errorId = $fieldName . '-error';
        $helpId = $helpText ? $fieldName . '-help' : null;

        $groupClass = 'form-group' . ($hasError ? ' form-group--error' : '');
        $html = '<div class="' . $groupClass . '">';
        $html .= '<label for="' . escape($fieldName) . '">';
        $html .= escape($fieldLabel);

        if ($isRequired) {
            // The asterisk is colour-coded decoration. The real required signal is the input's required attribute plus the visually-hidden text for screen readers.
            $html .= ' <span class="required" aria-hidden="true">*</span>';
            $html .= '<span class="visually-hidden"> (required)</span>';
        }

        $html .= '</label>';

        switch ($fieldType) {
            case 'text':
            case 'email':
            case 'phone':
                $html .= $this->renderTextInput($field, $value, $readonly);
                break;

            case 'number':
                $html .= $this->renderNumberInput($field, $value, $readonly);
                break;

            case 'date':
                $html .= $this->renderDateInput($field, $value, $readonly);
                break;

            case 'datetime':
                $html .= $this->renderDatetimeInput($field, $value, $readonly);
                break;

            case 'textarea':
                $html .= $this->renderTextarea($field, $value, $readonly);
                break;

            case 'select':
                $html .= $this->renderSelect($field, $value, $options, $readonly);
                break;

            case 'radio':
                $html .= $this->renderRadio($field, $value, $options, $readonly);
                break;

            case 'checkbox':
                $html .= $this->renderCheckbox($field, $value, $options, $readonly);
                break;

            case 'boolean':
                $html .= $this->renderBooleanCheckbox($field, $value, $readonly);
                break;

            default:
                $html .= $this->renderTextInput($field, $value, $readonly);
        }

        if ($helpText) {
            $html .= '<div id="' . escape($helpId) . '" class="form-help">' . escape($helpText) . '</div>';
        }

        if ($hasError) {
            $html .= '<div id="' . escape($errorId) . '" class="form-error" role="alert">';
            $html .= escape($this->fieldErrors[$fieldName]);
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Add ARIA attributes to an input. Centralised so every input type emits the same accessible markup without duplicating the logic.
     */
    private function applyA11yAttrs(array $field, array $attrs): array
    {
        $name = $field['field_name'];
        $describedBy = [];

        if (!empty($field['help_text'])) {
            $describedBy[] = $name . '-help';
        }

        if (isset($this->fieldErrors[$name])) {
            $attrs['aria-invalid'] = 'true';
            $describedBy[] = $name . '-error';
        }

        if (!empty($field['is_required'])) {
            $attrs['aria-required'] = 'true';
        }

        if (!empty($describedBy)) {
            $attrs['aria-describedby'] = implode(' ', $describedBy);
        }

        return $attrs;
    }

    private function renderTextInput($field, $value, $readonly)
    {
        $type = $field['field_type'] === 'email' ? 'email' :
            ($field['field_type'] === 'phone' ? 'tel' : 'text');

        $attrs = [
            'type' => $type,
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'value' => escape($value),
            'class' => 'form-control'
        ];

        if ($field['is_required']) {
            $attrs['required'] = 'required';
        }

        if ($field['max_length']) {
            $attrs['maxlength'] = $field['max_length'];
        }

        if ($readonly) {
            $attrs['readonly'] = 'readonly';
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        return '<input ' . $this->buildAttributes($attrs) . '>';
    }

    private function renderNumberInput($field, $value, $readonly)
    {
        $attrs = [
            'type' => 'number',
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'value' => escape($value),
            'class' => 'form-control'
        ];

        if ($field['is_required']) {
            $attrs['required'] = 'required';
        }

        if ($readonly) {
            $attrs['readonly'] = 'readonly';
        }

        // Pull min/max/step out of validation_rules JSON if present.
        if ($field['validation_rules']) {
            $rules = json_decode($field['validation_rules'], true);
            if (isset($rules['min'])) {
                $attrs['min'] = $rules['min'];
            }
            if (isset($rules['max'])) {
                $attrs['max'] = $rules['max'];
            }
            if (isset($rules['step'])) {
                $attrs['step'] = $rules['step'];
            }
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        return '<input ' . $this->buildAttributes($attrs) . '>';
    }

    private function renderDateInput($field, $value, $readonly)
    {
        $attrs = [
            'type' => 'date',
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'value' => escape($value),
            'class' => 'form-control'
        ];

        if ($field['is_required']) {
            $attrs['required'] = 'required';
        }

        if ($readonly) {
            $attrs['readonly'] = 'readonly';
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        return '<input ' . $this->buildAttributes($attrs) . '>';
    }

    private function renderDatetimeInput($field, $value, $readonly)
    {
        $attrs = [
            'type' => 'datetime-local',
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'value' => escape($value),
            'class' => 'form-control'
        ];

        if ($field['is_required']) {
            $attrs['required'] = 'required';
        }

        if ($readonly) {
            $attrs['readonly'] = 'readonly';
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        return '<input ' . $this->buildAttributes($attrs) . '>';
    }

    private function renderTextarea($field, $value, $readonly)
    {
        $attrs = [
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'class' => 'form-control',
            'rows' => '4'
        ];

        if ($field['is_required']) {
            $attrs['required'] = 'required';
        }

        if ($field['max_length']) {
            $attrs['maxlength'] = $field['max_length'];
        }

        if ($readonly) {
            $attrs['readonly'] = 'readonly';
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        return '<textarea ' . $this->buildAttributes($attrs) . '>' . escape($value) . '</textarea>';
    }

    private function renderSelect($field, $value, $options, $readonly)
    {
        $attrs = [
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'class' => 'form-control'
        ];

        if ($field['is_required']) {
            $attrs['required'] = 'required';
        }

        if ($readonly) {
            $attrs['disabled'] = 'disabled';
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        $html = "<select {$this->buildAttributes($attrs)}>";

        if (!$field['is_required']) {
            $html .= '<option value="">-- Select --</option>';
        }

        if (is_array($options)) {
            foreach ($options as $optValue => $optLabel) {
                $selected = ($value == $optValue) ? ' selected' : '';
                $html .= '<option value="' . escape($optValue) . '"' . $selected . '>';
                $html .= escape($optLabel);
                $html .= '</option>';
            }
        }

        $html .= '</select>';

        return $html;
    }

    private function renderRadio($field, $value, $options, $readonly)
    {
        $html = '<div class="radio-group">';

        if (is_array($options)) {
            foreach ($options as $optValue => $optLabel) {
                $checked = ($value == $optValue) ? ' checked' : '';
                $id = $field['field_name'] . '_' . $optValue;

                $attrs = [
                    'type' => 'radio',
                    'id' => $id,
                    'name' => $field['field_name'],
                    'value' => escape($optValue)
                ];

                if ($checked) {
                    $attrs['checked'] = 'checked';
                }

                if ($readonly) {
                    $attrs['disabled'] = 'disabled';
                }

                $html .= '<div class="radio-option">';
                $html .= "<input {$this->buildAttributes($attrs)}>";
                $html .= "<label for=\"{$id}\">" . escape($optLabel) . '</label>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Multi-select checkboxes. Stored value can be either a JSON-encoded array (from earlier saves) or a plain PHP array.
     */
    private function renderCheckbox($field, $value, $options, $readonly)
    {
        $html = '<div class="checkbox-group">';

        $selectedValues = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($selectedValues)) {
            $selectedValues = [];
        }

        if (is_array($options)) {
            foreach ($options as $optValue => $optLabel) {
                $checked = in_array($optValue, $selectedValues) ? ' checked' : null;
                $id = $field['field_name'] . '_' . $optValue;

                $attrs = [
                    'type' => 'checkbox',
                    'id' => $id,
                    'name' => "{$field['field_name']}[]",
                    'value' => escape($optValue)
                ];

                if ($checked) {
                    $attrs['checked'] = 'checked';
                }

                if ($readonly) {
                    $attrs['disabled'] = 'disabled';
                }

                $html .= '<div class="checkbox-option">';
                $html .= "<input {$this->buildAttributes($attrs)}>";
                $html .= "<label for=\"{$id}\">" . escape($optLabel) . '</label>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    private function renderBooleanCheckbox($field, $value, $readonly)
    {
        $checked = ($value == '1' || $value === true || $value === 'true') ? ' checked' : '';

        $attrs = [
            'type' => 'checkbox',
            'id' => $field['field_name'],
            'name' => $field['field_name'],
            'value' => '1'
        ];

        if ($checked) {
            $attrs['checked'] = 'checked';
        }

        if ($readonly) {
            $attrs['disabled'] = 'disabled';
        }

        $attrs = $this->applyA11yAttrs($field, $attrs);

        return '<input ' . $this->buildAttributes($attrs) . '>';
    }

    private function buildAttributes($attrs)
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $parts[] = $key;
            } else {
                $parts[] = $key . '="' . $value . '"';
            }
        }
        return implode(' ', $parts);
    }
}
