<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts::display')]
class extends Component
{
    public $theme = 'template-one'; //default theme

    public function mount()
    {
        $setting = DB::table('settings')->where('key', 'display_template')->first();
        if ($setting) {
            $value = json_decode($setting->value, true);
            $this->theme = $value['default'] ?? 'template-one';
        }
    }
};
?>

<div>
    @if($theme == 'template-one')
        <livewire:pages::themes.one />
    @else
        <livewire:pages::themes.two />
    @endif
</div>