<?php

namespace App\Support\Csv;

use Generator;
use RuntimeException;

/**
 * Leitor tolerante de CSV: detecta delimitador (',' ';' ou tab) e encoding (UTF-8 vs.
 * ISO-8859-1/Windows-1252, comuns em exports da Receita Federal) a partir de uma amostra do
 * arquivo, sem carregar o arquivo inteiro em memória.
 */
class CsvFileReader
{
    private string $delimiter;

    private string $encoding;

    /** @var array<int, string> */
    private array $header;

    public function __construct(private readonly string $path)
    {
        $sample = (string) @file_get_contents($this->path, false, null, 0, 8192);
        $this->encoding = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';

        $firstLine = $this->convertEncoding((string) strtok($sample, "\n"));
        $this->delimiter = $this->detectDelimiter($firstLine);
        $this->header = $this->parseLine($firstLine);
    }

    /** @return array<int, string> */
    public function header(): array
    {
        return $this->header;
    }

    /** @return Generator<int, array<string, string|null>> */
    public function rows(): Generator
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir o arquivo CSV em {$this->path}.");
        }

        fgets($handle); // pula o cabeçalho

        $columnCount = count($this->header);

        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                $fields = $this->parseLine($this->convertEncoding($line));
                $fields = array_pad(array_slice($fields, 0, $columnCount), $columnCount, null);

                yield array_combine($this->header, $fields);
            }
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $best = ',';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    /** @return array<int, string> */
    private function parseLine(string $line): array
    {
        $fields = str_getcsv($line, $this->delimiter);

        return array_map(static fn ($field) => trim((string) $field), $fields);
    }

    private function convertEncoding(string $value): string
    {
        if ($this->encoding === 'UTF-8') {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', $this->encoding);
    }
}
