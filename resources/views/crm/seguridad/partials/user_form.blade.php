@php
    $selectedType = old('user_type', $tipoActual ?? 'interno');
    $selectedRole = (int) old('roles_id', $rolActual ?? 0);
    $selectedCompany = old('empresa_id', isset($user) ? $user->empresa_id : '');
@endphp

<label for="user_type">Tipo de usuario</label>
<select id="user_type" name="user_type" required style="margin-bottom:14px">
    <option value="interno" @selected($selectedType === 'interno')>Interno</option>
    <option value="b2b" @selected($selectedType === 'b2b')>B2B</option>
    <option value="b2c_asistido" @selected($selectedType === 'b2c_asistido') @disabled(!$b2cConfigured)>B2C asistido{{ $b2cConfigured ? '' : ' — configuración pendiente' }}</option>
</select>

<label for="name">Nombre</label>
<input id="name" name="name" value="{{ old('name', $user->name ?? '') }}" required maxlength="255" style="margin-bottom:14px">

<label for="email">Correo electrónico</label>
<input id="email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required maxlength="255" style="margin-bottom:14px">

<label for="password">{{ isset($user) ? 'Nueva contraseña temporal (opcional)' : 'Contraseña temporal' }}</label>
<input id="password" type="password" name="password" {{ isset($user) ? '' : 'required' }} minlength="8" autocomplete="new-password" style="margin-bottom:14px">

<div id="company_field">
    <label for="empresa_id">Empresa</label>
    <select id="empresa_id" name="empresa_id" style="margin-bottom:14px">
        <option value="">Selecciona una empresa</option>
        @foreach($empresas as $empresa)
            <option value="{{ $empresa->id }}" @selected((string) $selectedCompany === (string) $empresa->id)>{{ $empresa->nombre }}</option>
        @endforeach
    </select>
</div>

<div id="role_field">
    <label for="roles_id">Rol permitido</label>
    <select id="roles_id" name="roles_id" style="margin-bottom:14px">
        <option value="">Selecciona un rol</option>
        <optgroup label="Roles internos" data-role-group="interno">
            @foreach($internalRoles as $role)
                @if($isSysadmin || !in_array($role->slug, ['sysadmin', 'admin'], true))
                    <option value="{{ $role->id }}" data-type="interno" @selected($selectedRole === (int) $role->id)>{{ $role->name }} — {{ $role->slug }}</option>
                @endif
            @endforeach
        </optgroup>
        <optgroup label="Roles empresariales" data-role-group="b2b">
            @foreach($businessRoles as $role)
                <option value="{{ $role->id }}" data-type="b2b" @selected($selectedRole === (int) $role->id)>{{ $role->name }} — {{ $role->slug }}</option>
            @endforeach
        </optgroup>
    </select>
</div>

<label for="status">Estado</label>
<select id="status" name="status" required style="margin-bottom:8px">
    <option value="active">Activo</option>
</select>
<p class="muted" style="margin-bottom:20px">La inactivación requiere soporte persistente posterior; actualmente no existe un campo de estado en users.</p>

@if(!$b2cConfigured)
    <div class="alert alert-info">La creación B2C asistida está deshabilitada porque no existe una empresa pública B2C válida en <code>services.b2c.empresa_id</code>.</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('user_type');
    const companyField = document.getElementById('company_field');
    const company = document.getElementById('empresa_id');
    const roleField = document.getElementById('role_field');
    const role = document.getElementById('roles_id');
    const options = Array.from(role.querySelectorAll('option[data-type]'));

    function updateFields() {
        const selected = type.value;
        companyField.style.display = selected === 'b2b' ? 'block' : 'none';
        company.required = selected === 'b2b';
        roleField.style.display = selected === 'b2c_asistido' ? 'none' : 'block';
        role.required = selected !== 'b2c_asistido';

        options.forEach(function (option) {
            option.hidden = option.dataset.type !== selected;
            option.disabled = option.dataset.type !== selected;
        });

        if (role.selectedOptions.length && role.selectedOptions[0].disabled) {
            role.value = '';
        }
    }

    type.addEventListener('change', updateFields);
    updateFields();
});
</script>
@endpush
