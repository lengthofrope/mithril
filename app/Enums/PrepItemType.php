<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of meeting preparation items.
 */
enum PrepItemType: string
{
    case AgendaItem = 'agenda_item';
    case Question = 'question';
    case Action = 'action';
}
