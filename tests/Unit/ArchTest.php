<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

arch('enums are string backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('models extend Eloquent Model')
    ->expect('App\Models')
    ->toBeClasses()
    ->toExtend(Model::class);

arch('controllers have Controller suffix')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('livewire components extend Livewire Component')
    ->expect('App\Livewire')
    ->toExtend(Component::class)
    ->ignoring('App\Livewire\Actions');

arch('application has no debugging statements')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
