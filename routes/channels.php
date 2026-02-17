<?php

use App\Broadcasting\IoTDashboardOrganizationChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('iot-dashboard.organization.{organizationId}', IoTDashboardOrganizationChannel::class);
Broadcast::channel('private-iot-dashboard.organization.{organizationId}', IoTDashboardOrganizationChannel::class);
