@props(['id','labelledby'=>null])
<dialog id="{{ $id }}" class="z-dialog" @if($labelledby) aria-labelledby="{{ $labelledby }}" @endif><div class="z-dialog__body">{{ $slot }}</div></dialog>
