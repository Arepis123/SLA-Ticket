<?php

namespace App\Enums;

/**
 * The pages with their own guided tour. Each is offered to a client the first
 * time they open that page, independently of the others.
 */
enum Tour: string
{
    case Dashboard = 'dashboard';
    case Tickets = 'tickets';
    case ReportBug = 'report-bug';
}
