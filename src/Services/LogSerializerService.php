<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;
use InvalidArgumentException;
use stdClass;

final class LogSerializerService
{
    /**
     * Sérialise un LogRecord en JSON.
     */
    public function serialize(LogRecord $record): string
    {
        $logEntry = [
            'time' => $record->time,
            'level' => $record->level->value,
            'data' => [
                'type' => $record->data->type,
                'payload' => $this->serializePayload($record->data->payload),
            ],
        ];

        return json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Sérialise un payload en array pour JSON.
     */
    private function serializePayload(MixedPayloadCollection $payload): array
    {
        $result = [];
        foreach ($payload as $item) {
            if ($item instanceof AbstractRecord) {
                $result[] = $item->toArray();
            } elseif ($item instanceof TypedCollection) {
                $result[] = $this->serializeTypedRecords($item);
            } elseif ($item instanceof stdClass) {
                $result[] = (array) $item;
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Sérialise une collection TypedRecords.
     */
    private function serializeTypedRecords(TypedCollection $collection): array
    {
        $result = [];
        foreach ($collection as $item) {
            if ($item instanceof AbstractRecord) {
                $result[] = $item->toArray();
            } elseif ($item instanceof TypedCollection) {
                $result[] = $this->serializeTypedRecords($item);
            } elseif ($item instanceof stdClass) {
                $result[] = (array) $item;
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Désérialise une ligne JSON en LogRecord.
     */
    public function deserialize(string $line): ?LogRecord
    {
        $data = json_decode($line);

        if (! is_object($data)) {
            return null;
        }

        if (! isset($data->time, $data->level, $data->data)) {
            return null;
        }

        $level = LogLevel::fromValue($data->level);
        if ($level === null) {
            return null;
        }

        if (! isset($data->data->type, $data->data->payload)) {
            return null;
        }

        if (! is_array($data->data->payload)) {
            return null;
        }

        try {
            $payload = new MixedPayloadCollection;

            foreach ($data->data->payload as $item) {
                if (is_array($item)) {
                    // Un tableau devient un stdClass (objet simple)
                    $payload->add((object) $item);
                } elseif (is_object($item)) {
                    // Déjà un objet (stdClass de json_decode)
                    $payload->add($item);
                } else {
                    // Scalaire
                    $payload->add($item);
                }
            }

            $logData = new LogDataRecord(
                type: $data->data->type,
                payload: $payload,
            );
        } catch (InvalidArgumentException $e) {
            return null;
        }

        return new LogRecord(
            time: $data->time,
            level: $level,
            data: $logData,
        );
    }

    /**
     * Valide qu'une ligne JSON est un log valide.
     */
    public function isValidLogLine(string $line): bool
    {
        $data = json_decode($line);

        if (! is_object($data)) {
            return false;
        }

        if (! isset($data->time, $data->level, $data->data)) {
            return false;
        }

        if (! isset($data->data->type, $data->data->payload)) {
            return false;
        }

        $level = LogLevel::fromValue($data->level);

        return $level !== null;
    }
}
