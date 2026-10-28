<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case Ongoing = 'ongoing';
    case Done = 'done';
}