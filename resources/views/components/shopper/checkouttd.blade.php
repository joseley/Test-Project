@if($shopper['status']['name'] == 'Active' && empty($shopper['check_out']))
    <form method='POST' action="{{ url()->current() . "/checkout" }}">
        @method('PATCH')
        @csrf

        <input type="hidden" id="shopperUuid" name="shopper_uuid" value="{{ $shopper['uuid'] }}">

        <button class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition" type="submit">
            Check-out
        </button>
    </form>
@else
    {{ $shopper['check_out'] }}
@endif
