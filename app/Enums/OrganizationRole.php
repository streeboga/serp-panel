<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Analyst = 'analyst';
    case Viewer = 'viewer';
}
