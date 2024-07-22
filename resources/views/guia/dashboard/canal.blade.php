@if ($objeto->canal=='RET')
    <td>{{ $objeto->contacto_d }}</td>
    <td>{{ $objeto->contacto }}</td>
    <td style="display:none;">{{ $objeto->cp_d }}</td>
    <td style="display:none;" >{{ $objeto->ciudad_d }}</td>
    <td style="display:none;" >{{ $objeto->cp }}</td>
    <td style="display:none;" >{{ $objeto->ciudad }}</td>
@else
    <td>{{ $objeto->contacto }}</td>
    <td>{{ $objeto->contacto_d }}</td>
    <td style="display:none;" >{{ $objeto->cp }}</td>
    <td style="display:none;" >{{ $objeto->ciudad }}</td>
    <td style="display:none;">{{ $objeto->cp_d }}</td>
    <td style="display:none;" >{{ $objeto->ciudad_d }}</td>
@endif


