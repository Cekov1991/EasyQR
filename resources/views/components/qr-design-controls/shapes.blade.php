{{-- The shape controls: modules, finder corners and finder eye. --}}
<div class="eq-controls-group" data-qr-section="shapes">
    <x-qr-design-controls.options setting="dot" label="Modules" :options="['square' => 'Square', 'rounded' => 'Rounded', 'dots' => 'Dots', 'fluid' => 'Fluid']" />
    <x-qr-design-controls.options setting="corner" label="Corners" :options="['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle']" />
    <x-qr-design-controls.options setting="eye" label="Eye" :options="['square' => 'Square', 'rounded' => 'Rounded', 'dot' => 'Dot']" />
</div>
