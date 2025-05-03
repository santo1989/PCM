{{-- <button type="submit" class="btn btn-sm" style="background-image: linear-gradient(#40c47c,#40c47c,#40c47c);">
    {{ $slot }}
</button> --}}

{{-- <button type="submit" class="btn btn-sm" style="background-color: 
  @if ($slot === 'save')
    green
  @elseif ($slot === 'create')
    blue
  @elseif ($slot === 'edit')
    orange
  @elseif ($slot === 'delete')
    red
  @endif
">
  {{ $slot }}
</button> --}}

{{-- <button type="submit" class="btn btn-sm" style="background-image: url(
    @if ($slot === 'Save')
        {{ asset('images/save.png') }}
    @elseif ($slot === 'Create')
        {{ asset('images/create.png') }}
    @elseif ($slot === 'Edit')
        {{ asset('images/edit.png') }}
    @elseif ($slot === 'Delete')
        {{ asset('images/delete.png') }}
    @endif
); border-color:
    @if ($slot === 'Save')
        green
    @elseif ($slot === 'Create')
        blue
    @elseif ($slot === 'Edit')
        orange
    @elseif ($slot === 'Delete')
        red
    @endif
;">
  {{ $slot }}
</button> --}}

{{-- <button type="submit" class="btn btn-sm btn-outline-{{ strtolower($slot) }}" style="background-image: url(
    @if ($slot === 'Save')
        {{ asset('images/save.png') }}
    @elseif ($slot === 'Create')
        {{ asset('images/create.png') }}
    @elseif ($slot === 'Edit')
        {{ asset('images/edit.png') }}
    @elseif ($slot === 'Delete')
        {{ asset('images/delete.png') }}
    @endif
);">
  {{ $slot }}
</button> --}}

{{-- @php
    // $class = '';
    $background = '';
    switch ($slot) {
        case 'Save':
            // $class = 'btn-outline-warning';
            $background = 'Create.png';
            break;
        case 'Create':
            // $class = 'btn-outline-secondary';
            $background = 'Create.png';
            break;
        case 'Edit':
            // $class = 'btn-outline-success';
            $background = 'Edit.png';
            break;
        case 'Delete':
            // $class = 'btn-outline-danger';
            $background = 'Delete.png';
            break;
        case 'Show':
            // $class = 'btn-outline-info';
            $background = 'Show.png';
            break;
        default:
            // $class = 'btn-outline-primary';
            $background = 'Show.png';
            break;
    }
@endphp

<button type="submit" class="btn btn-sm {{ $class }}"
    style="background-image: url({{ asset('images/assets/' . $background) }})">
    {{ $slot }}
</button> --}}
{{-- @props(['type']) --}}

{{-- @php
            $class = '';
            $imgSrc = '';
            $alt = '';
    switch ($slot) {
        case 'save':
            $class = 'btn btn-sm btn-outline-warning';
            $imgSrc = 'Save.png';
            $alt = 'Save';
            break;
        case 'create':
            $class = 'btn btn-sm btn-outline-secondary';
            $imgSrc = 'Create.png';
            $alt = 'Create';
            break;
        case 'edit':
            $class = 'btn btn-sm btn-outline-success';
            $imgSrc = 'Edit.png';
            $alt = 'Edit';
            break;
        case 'delete':
            $class = 'btn btn-sm btn-outline-danger';
            $imgSrc = 'Delete.png';
            $alt = 'Delete';
            break;
        case 'show':
            $class = 'btn btn-sm btn-outline-info';
            $imgSrc = 'Show.png';
            $alt = 'Show';
            break;
        default:
            $class = 'btn btn-sm';
            $imgSrc = '';
            $alt = '';
            break;
    }
@endphp

<button type="submit" class="{{ $class }}">
    @if ($imgSrc)
        <img src="{{ asset('images/assets/' . $imgSrc) }}" alt="{{ $alt }}">
    @else
        {{ $slot }}
    @endif
</button> --}}

{{-- <button type="submit" class="btn btn-sm 
        @if($slot == 'Save') btn-outline-info
        @elseif($slot == 'Create') btn-outline-secondary
        @elseif($slot == 'Edit') btn-outline-success
        @elseif($slot == 'Delete') btn-outline-danger
        @elseif($slot == 'Show') btn-outline-info
        @endif">
    <span class="fa-stack fa-sm">
        <i class="fas fa-circle fa-stack-2x"></i>
        <i class="fas fa-{{ $slot == 'Save' ? 'save' : ($slot == 'Create' ? 'plus' : ($slot == 'Edit' ? 'edit' : ($slot == 'Delete' ? 'trash' : 'eye'))) }} fa-stack-1x fa-inverse"></i>
    </span>
    {{ $slot }}
</button> --}}

<button type="submit" class="btn btn-sm {{ $slot === 'Save' ? 'btn-outline-warning' : ($slot === 'Create' ? 'btn-outline-secondary' : ($slot === 'Edit' ? 'btn-outline-success' : ($slot === 'Delete' ? 'btn-outline-danger' : 'btn-outline-info'))) }}">
    <span>{{ $slot }}</span>
</button>

<style>
.btn-outline-warning:hover {
    background: linear-gradient(45deg, #FCA311, #FFD3B6);
    color: #fff;
}
.btn-outline-secondary:hover {
    background: linear-gradient(45deg, #545454, #808080);
    color: #fff;
}
.btn-outline-success:hover {
    background: linear-gradient(45deg, #28A745, #6FCE9C);
    color: #fff;
}
.btn-outline-danger:hover {
    background: linear-gradient(45deg, #DC3545, #FFA0A0);
    color: #fff;
}
.btn-outline-info:hover {
    background: linear-gradient(45deg, #17A2B8, #9ADCEC);
    color: #fff;
}

.btn:hover span {
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

</style>



