<?php
declare(strict_types=1);
namespace SharpAPI\Core\Enums;

/**
 * Enum SharpApiJobStatusEnum
 *
 * Represents the status of a job as returned by the SharpAPI.
 */
enum SharpApiJobStatusEnum: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case SUCCESS = 'success';

    /**
     * Get a user-friendly label for each status.
     * @api
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::PENDING => 'Pending',
            self::FAILED => 'Failed',
            self::SUCCESS => 'Success',
        };
    }

    /**
     * Get a color associated with each status for UI representation.
     *
     * @return string
     * @api
     */
    public function color(): string
    {
        return match ($this) {
            self::NEW => 'gray',
            self::PENDING => 'yellow',
            self::FAILED => 'red',
            self::SUCCESS => 'green',
        };
    }
}
