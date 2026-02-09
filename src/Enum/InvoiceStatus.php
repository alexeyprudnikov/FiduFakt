<?php

namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case DISPATCHABLE = 'dispatchable'; // über API zu übergeben wenn die Rechung aus der API an den Kunde direkt versandt werden soll
    case ISSUED = 'issued';     // PDF wurde generiert
    case SENT = 'sent';         // Per Mail an Kunden
    case PAID = 'paid';         // Zahlung eingegangen
    case OVERDUE = 'overdue';   // Zahlungsziel überschritten
    case CANCELLED = 'cancelled';
}
