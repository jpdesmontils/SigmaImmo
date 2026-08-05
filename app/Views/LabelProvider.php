<?php
class LabelProvider
{
    private $labels;

    public function __construct($file = null)
    {
        $path = $file ?: dirname(dirname(__DIR__)) . '/resources/labels/fr.json';
        $decoded = json_decode(file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('labels.invalid_json');
        }
        $this->labels = $decoded;
    }

    public function all()
    {
        return $this->labels;
    }

    public function get($key, $default = null)
    {
        $value = $this->value($this->labels, $key);
        if ($value === null) {
            return $default === null ? $key : $default;
        }
        return $value;
    }

    public function section($key)
    {
        $value = $this->value($this->labels, $key);
        return is_array($value) ? $value : array();
    }

    private function value(array $source, $key)
    {
        $parts = explode('.', $key);
        $value = $source;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
