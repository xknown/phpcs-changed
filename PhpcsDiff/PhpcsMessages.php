<?php
declare(strict_types=1);

namespace PhpcsDiff;

use PhpcsDiff\PhpcsMessage;

class PhpcsMessages {
	private $messages = [];

	public function __construct(array $messages) {
		foreach($messages as $message) {
			if (! $message instanceof PhpcsMessage) {
				throw new \Exception('Each message in a PhpcsMessages object must be a PhpcsMessage; found ' . var_export($message, true));
			}
		}
		$this->messages = $messages;
	}

	public static function fromArrays(array $messages, string $file = null): self {
		return new self(array_map(function($messageArray) use ($file) {
			if (is_array($messageArray)) {
				return new PhpcsMessage($messageArray['line'] ?? null, $file ?? 'STDIN', $messageArray);
			}
			return $messageArray;
		}, $messages));
	}

	public static function fromPhpcsJson(string $messages): self {
		$parsed = json_decode($messages, true);
		if (! $parsed) {
			throw new \Exception('Failed to decode phpcs JSON');
		}
		if (! isset($parsed['files']) || ! is_array($parsed['files'])) {
			throw new \Exception('Failed to find files in phpcs JSON');
		}
		$fileNames = array_map(function($fileName) {
			return $fileName;
		}, array_keys($parsed['files']));
		if (count($fileNames) < 1) {
			throw new \Exception('Failed to find files in phpcs JSON');
		}
		$file = $fileNames[0];
		if (! isset($parsed['files'][$file]['messages'])) {
			throw new \Exception('Failed to find messages in phpcs JSON');
		}
		if (! is_array($parsed['files'][$file]['messages'])) {
			throw new \Exception('Failed to find messages array in phpcs JSON');
		}
		return self::fromArrays($parsed['files'][$file]['messages'], $file);
	}

	public function getMessages(): array {
		return $this->messages;
	}

	public function getLineNumbers(): array {
		return array_map(function($message) {
			return $message->getLineNumber();
		}, $this->messages);
	}

	public function toPhpcsJson(): string {
		$file = isset($this->messages[0]) ? $this->messages[0]->getFile() : 'STDIN';
		$messages = array_map(function($message) {
			return $message->toPhpcsArray();
		}, $this->messages);
		$dataForJson = [
			'totals' => [
				'errors' => 0,
				'warnings' => count($messages),
				'fixable' => 0,
			],
			'files' => [
				$file => [
					'errors' => 0,
					'warnings' => count($messages),
					'messages' => $messages,
				],
			],
		];
		return json_encode($dataForJson, JSON_UNESCAPED_SLASHES);
	}
}
