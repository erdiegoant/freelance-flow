<?php

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\Notification;

arch('models extend Eloquent and use HasFactory')
    ->expect('App\Models')
    ->toExtend(Model::class)
    ->toUse(HasFactory::class)
    ->ignoring('App\Models\User'); // User is the framework default — excluded from our domain models

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('api controllers have the Controller suffix and extend the base Controller')
    ->expect('App\Http\Controllers\Api')
    ->toHaveSuffix('Controller')
    ->toExtend(Controller::class);

arch('services have the Service suffix')
    ->expect('App\Services')
    ->toHaveSuffix('Service');

arch('jobs implement ShouldQueue')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueue::class);

arch('notifications extend the base Notification class')
    ->expect('App\Notifications')
    ->toExtend(Notification::class);

arch('form requests extend FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch('no debug statements left in production code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);
