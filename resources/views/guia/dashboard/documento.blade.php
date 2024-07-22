
@if ($objeto->ltd_id==1)
	@foreach(explode('|', $objeto->documento) as $documento) 
    	<a href="{{ $documento }}" target="_blank" rel="noopener noreferrer"><i class="text-info tx-20 fa fa-archive" data-toggle="tooltip" title="" data-original-title="fa fa-archive"></i></a>
  	@endforeach
	
@else
	<a href="{{ Storage::url($objeto->documento) }}" target="_blank" rel="noopener noreferrer"><i class="text-info tx-20 fa fa-archive" data-toggle="tooltip" title="" data-original-title="fa fa-archive"></i></a>

@endif
