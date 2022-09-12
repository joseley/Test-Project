<x-table-column>
    <x-shopper.status :shopper="$shopper"/>
</x-table-column>

<x-table-column>
    {{ $shopper['first_name'] }} {{ $shopper['last_name'] }}
</x-table-column>

<x-table-column>
    {{ $shopper['email'] }}
</x-table-column>

<x-table-column>
    {{ $shopper['check_in'] }}
</x-table-column>

<x-table-column>
    <x-shopper.checkouttd :shopper="$shopper"></x-checkouttd>
</x-table-column>

{{--<x-table-column>--}}

{{--</x-table-column>--}}
