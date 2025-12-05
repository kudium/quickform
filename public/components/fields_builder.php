<?php
// Reusable fields builder component
// Expects variables:
// - $builder_fields: array of existing fields (each: name,label,type,required?,options[])
// - $builder_editable_names: bool - when true, name input shown for pre-rendered fields; when false, name is fixed/hidden for existing fields
// - $builder_auto_add: bool - when true and no existing fields, auto-add one blank card on load

if (!isset($builder_fields) || !is_array($builder_fields)) { $builder_fields = []; }
if (!isset($builder_editable_names)) { $builder_editable_names = true; }
if (!isset($builder_auto_add)) { $builder_auto_add = false; }

$allowedTypes = function_exists('getAllowedFieldTypes') ? getAllowedFieldTypes() : ['text','password','textarea','file','select','select_multiple','radio','checkbox','checkbox_group','email','number','url','tel','date','time','datetime-local'];
?>
<div id="fields" class="space-y-4">
  <?php foreach ($builder_fields as $fld): ?>
    <?php $t = $fld['type'] ?? 'text'; $needsOpts = in_array($t, ['select','select_multiple','radio','checkbox_group'], true); ?>
    <div class="relative rounded-lg border border-gray-200 p-4 bg-white shadow-sm field-card">
      <div class="absolute top-1 right-1 flex items-center gap-1">
        <button type="button" class="text-gray-400 hover:text-gray-700 px-1 py-0.5 btn-up" title="Move up">↑</button>
        <button type="button" class="text-gray-400 hover:text-gray-700 px-1 py-0.5 btn-down" title="Move down">↓</button>
        <button type="button" class="text-gray-400 hover:text-red-600 px-1 py-0.5" aria-label="Remove field" title="Delete field" onclick="this.closest('.field-card').remove()">×</button>
      </div>
      <?php if (!$builder_editable_names): ?>
        <input type="hidden" name="field_name[]" value="<?php echo htmlspecialchars($fld['name'] ?? ''); ?>" />
      <?php endif; ?>
      <div class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
            <input name="field_label[]" value="<?php echo htmlspecialchars($fld['label'] ?? ''); ?>" required autocapitalize="on" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition fld-label" />
            <?php if (!$builder_editable_names): ?>
              <p class="text-[11px] text-gray-500 mt-1">Name: <span class="font-mono text-gray-800"><?php echo htmlspecialchars($fld['name'] ?? ''); ?></span></p>
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="field_type[]" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition fld-type">
              <?php foreach ($allowedTypes as $opt): ?>
                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($opt === $t) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if ($builder_editable_names): ?>
          <details class="mt-1">
            <summary class="text-sm text-gray-600 cursor-pointer select-none">More</summary>
            <div class="mt-3">
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input name="field_name[]" value="<?php echo htmlspecialchars($fld['name'] ?? ''); ?>" placeholder="auto from label (camelCase)" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition fld-name" />
            </div>
          </details>
        <?php endif; ?>
        <div class="flex items-center gap-2 mt-1">
          <input type="checkbox" name="field_required[]" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?php echo !empty($fld['required']) ? 'checked' : ''; ?> />
          <span class="text-sm text-gray-800">Required</span>
        </div>
        <div class="options-block <?php echo $needsOpts ? '' : 'hidden'; ?>">
          <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
          <textarea name="field_options[]" rows="3" placeholder="One option per line" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition"><?php echo htmlspecialchars(implode("\n", (array)($fld['options'] ?? []))); ?></textarea>
          <p class="text-xs text-gray-500 mt-1">Shown when type is select, select_multiple, radio, or checkbox_group.</p>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div class="mt-3 flex justify-start">
  <button type="button" id="addField" class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white text-gray-900 hover:bg-gray-50 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">+ Add field</button>
  <script>
    const fieldsEl = document.getElementById('fields');
    const addFieldBtn = document.getElementById('addField');
    function toCamelCase(input) {
      if (!input) return '';
      const parts = input.trim().replace(/[^A-Za-z0-9]+/g, ' ').split(' ').filter(Boolean);
      if (!parts.length) return '';
      const [first, ...rest] = parts;
      return first.toLowerCase() + rest.map(s => s.charAt(0).toUpperCase() + s.slice(1).toLowerCase()).join('');
    }
    function attachCardBehavior(card) {
      const labelInput = card.querySelector('.fld-label');
      const nameInput  = card.querySelector('.fld-name');
      const typeInput  = card.querySelector('.fld-type');
      const optsBlock  = card.querySelector('.options-block');
      const upBtn = card.querySelector('.btn-up');
      const downBtn = card.querySelector('.btn-down');
      let nameTouched = false;
      if (nameInput) { nameInput.addEventListener('input', () => { nameTouched = true; }); }
      if (labelInput && nameInput) {
        labelInput.addEventListener('input', () => {
          if (!nameTouched || !nameInput.value) {
            nameInput.value = toCamelCase(labelInput.value);
          }
        });
        labelInput.addEventListener('blur', () => {
          if (labelInput.value) {
            labelInput.value = labelInput.value.replace(/\S+/g, (w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
          }
        });
      }
      function toggleOptions() {
        const needs = ['select','select_multiple','radio','checkbox_group'];
        if (!typeInput || !optsBlock) return;
        if (needs.includes(typeInput.value)) {
          optsBlock.classList.remove('hidden');
        } else {
          optsBlock.classList.add('hidden');
        }
      }
      if (typeInput) {
        typeInput.addEventListener('change', toggleOptions);
        toggleOptions();
      }
      if (upBtn) {
        upBtn.addEventListener('click', () => {
          const prev = card.previousElementSibling;
          if (prev && prev.classList.contains('field-card')) {
            fieldsEl.insertBefore(card, prev);
          }
        });
      }
      if (downBtn) {
        downBtn.addEventListener('click', () => {
          const next = card.nextElementSibling;
          if (next && next.classList.contains('field-card')) {
            fieldsEl.insertBefore(next, card);
          }
        });
      }
    }
    if (addFieldBtn) {
      addFieldBtn.addEventListener('click', () => addField());
    }
    function addField() {
      const card = document.createElement('div');
      card.className = 'relative rounded-lg border border-gray-200 p-4 bg-white shadow-sm field-card';
      card.innerHTML = `
        <div class=\"absolute top-1 right-1 flex items-center gap-1\">\n          <button type=\"button\" class=\"text-gray-400 hover:text-gray-700 px-1 py-0.5 btn-up\" title=\"Move up\">↑</button>\n          <button type=\"button\" class=\"text-gray-400 hover:text-gray-700 px-1 py-0.5 btn-down\" title=\"Move down\">↓</button>\n          <button type=\"button\" class=\"text-gray-400 hover:text-red-600 px-1 py-0.5\" aria-label=\"Remove field\" title=\"Delete field\" onclick=\"this.closest('.field-card').remove()\">×</button>\n        </div>
        <div class=\"space-y-3\">
          <div class=\"grid grid-cols-1 md:grid-cols-2 gap-3\">
            <div>
              <label class=\"block text-sm font-medium text-gray-700 mb-1\">Label</label>
              <input name=\"field_label[]\" placeholder=\"e.g. Full Name\" required autocapitalize=\"on\" class=\"w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition fld-label\" />
            </div>
            <div>
              <label class=\"block text-sm font-medium text-gray-700 mb-1\">Type</label>
              <select name=\"field_type[]\" class=\"w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition fld-type\">
                <option value=\"text\">text</option>
                <option value=\"password\">password</option>
                <option value=\"textarea\">textarea</option>
                <option value=\"select\">select</option>
                <option value=\"select_multiple\">select_multiple</option>
                <option value=\"radio\">radio</option>
                <option value=\"checkbox\">checkbox</option>
                <option value=\"checkbox_group\">checkbox_group</option>
                <option value=\"email\">email</option>
                <option value=\"number\">number</option>
                <option value=\"url\">url</option>
                <option value=\"tel\">tel</option>
                <option value=\"date\">date</option>
                <option value=\"time\">time</option>
                <option value=\"datetime-local\">datetime-local</option>
                <option value=\"file\">file</option>
              </select>
            </div>
          </div>
          <details class=\"mt-1\">
            <summary class=\"text-sm text-gray-600 cursor-pointer select-none\">More</summary>
            <div class=\"mt-3\">
              <label class=\"block text-sm font-medium text-gray-700 mb-1\">Name</label>
              <input name=\"field_name[]\" placeholder=\"auto from label (camelCase)\" required class=\"w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition fld-name\" />
            </div>
          </details>
          <div class=\"flex items-center gap-2 mt-1\"> 
            <input type=\"checkbox\" name=\"field_required[]\" value=\"1\" class=\"rounded border-gray-300 text-indigo-600 focus:ring-indigo-500\" />
            <span class=\"text-sm text-gray-800\">Required</span>
          </div>
          <div class=\"options-block hidden\">
            <label class=\"block text-sm font-medium text-gray-700 mb-1\">Options</label>
            <textarea name=\"field_options[]\" rows=\"3\" placeholder=\"One option per line\" class=\"w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition\"></textarea>
            <p class=\"text-xs text-gray-500 mt-1\">Shown when type is select, select_multiple, radio, or checkbox_group.</p>
          </div>
        </div>
      `;
      fieldsEl.appendChild(card);
      attachCardBehavior(card);
    }
    // Attach behaviors to any pre-rendered cards
    document.querySelectorAll('.field-card').forEach(attachCardBehavior);
    <?php if ($builder_auto_add && empty($builder_fields)): ?>
      addField();
    <?php endif; ?>
  </script>
</div>
