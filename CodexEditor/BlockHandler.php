<?php

namespace CodexEditor;

/**
 * Class BlockHandler
 *
 * @package CodexEditor
 */
class BlockHandler
{
    /**
     * @var ConfigLoader|null
     */
    private $rules = null;

    /**
     * @var \HTMLPurifier_Config
     */
    private $sanitizer;

    /**
     * BlockHandler constructor.
     *
     * @param string $configuration_filename
     * @param \HTMLPurifier_Config $sanitizer
     *
     * @throws \Exception
     */
    public function __construct($configuration_filename, $sanitizer)
    {
        $this->rules = new ConfigLoader($configuration_filename);
        $this->sanitizer = $sanitizer;
    }

    /**
     * @param $blockType
     * @param $blockData
     *
     * @return array|bool
     * @throws \Exception
     */
    public function validate_block($blockType, $blockData)
    {
        /**
         * Default action for blocks that are not mentioned in a configuration
         */
        if (!array_key_exists($blockType, $this->rules->tools)) {
            return true;
        }

        $rule = $this->rules->tools[$blockType];

        return [
            'type' => $blockType,
            'data' => $this->validate($rule, $blockData)
        ];
    }

    /**
     * @param $rule
     * @param $blockData
     *
     * @return mixed
     * @throws \Exception
     */
    public function validate($rule, $blockData) {
        foreach ($rule as $key => $value) {
            /**
             * Check if required params are presented in data
             */
            if (($key != "-") && (isset($value['required'])?$value['required']:true)) {
                if (!isset($blockData[$key])) {
                    throw new \Exception("Not found required param $key");
                }
            }
        }

        foreach ($blockData as $key => $value) {
            /**
             * Check if there is not extra params
             */
            if (!is_integer($key) && !isset($rule[$key])) {
                throw new \Exception("Found extra param $key");
            }
        }

        foreach ($blockData as $key => $value) {
            if (is_integer($key)) {
                $key = "-";
            }

            $elementType = $rule[$key]['type'];

            if ($elementType == 'const') {
                if (!in_array($value, $rule[$key]['canBeOnly'])) {
                    throw new \Exception("$value const is invalid");
                }
            } elseif ($elementType == 'string') {
                $allowedTags = isset($rule[$key]['allowedTags']) ? $rule[$key]['allowedTags'] : '';
                $blockData[$key] = $this->getPurifier($allowedTags)->purify($value);
            } elseif ($elementType == 'int') {
                if (!is_integer($value)) {
                    throw new \Exception("$value is not integer");
                }
            } elseif ($elementType == 'array') {
                $blockData[$key] = $this->validate($rule[$key]['data'], $value);
            } else {
                throw new \Exception("Unhandled type: $elementType");
            }
        }

        return $blockData;
    }

    /**
     * @param $allowedTags
     *
     * @return \HTMLPurifier
     */
    private function getPurifier($allowedTags)
    {
        $sanitizer = clone $this->sanitizer;
        $sanitizer->set('HTML.Allowed', $allowedTags);

        $purifier = new \HTMLPurifier($sanitizer);

        return $purifier;
    }

    /**
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    private static function get($key, $default = null)
    {
        return isset($key) ? $key : $default;
    }
}