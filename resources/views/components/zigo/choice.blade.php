@props(['type'=>'checkbox','name','value'=>'1','checked'=>false,'disabled'=>false])
<label class="z-choice"><input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @checked($checked) @disabled($disabled)><span>{{ $slot }}</span></label>
