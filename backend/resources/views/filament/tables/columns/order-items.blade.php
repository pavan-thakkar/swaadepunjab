{{-- Ordered items as a vertical, wrapping list (own template so the table cannot squeeze it into one line) --}}
<div style="display:block;width:100%;min-width:270px;max-width:380px;white-space:normal;padding:10px 12px">
    {!! \App\Filament\Resources\OrderResource::itemsHtml($getRecord()) !!}
</div>
