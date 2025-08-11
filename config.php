<?php
if (!defined('INCLUDE_DIR')) die('No direct script access');

class AskAIPluginConfig extends PluginConfig
{
    function getOptions()
    {
        return [
            'general' => new SectionBreakField(['label' => 'General Settings']),
            'api_key' => new TextboxField([
                'label' => 'API Key',
                'hint'  => 'Get your API key from the Gemini AI console. (https://aistudio.google.com/)',
                'configuration' => ['size' => 60, 'length' => 200],
            ]),
            'model' => new ChoiceField([
                'label'   => 'Model',
                'hint'    => 'Select the AI model to use for generating responses.',
                'choices' => [
                    'gemini-2.5-pro'   => 'Gemini 2.5 Pro (Recommended)',
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
                    'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                ],
                'default' => 'gemini-2.5-flash',
            ]),
            'language' => new ChoiceField([
                'label'   => 'Response Language',
                'hint'    => 'Language used in AI output. "Auto" will follow the ticket language if detectable.',
                'choices' => [
                    'auto' => 'Auto (Follow ticket language)',
                    'id'   => 'Indonesian',
                    'en'   => 'English',
                    'ko'   => 'Korean',
                    'th'   => 'Thai',
                    'vi'   => 'Vietnamese',
                    'zh'   => 'Chinese (Simplified)',
                    'ar'   => 'Arabic',
                    'es'   => 'Spanish',
                ],
                'default' => 'id',
            ]),
        ];
    }

    function pre_save(&$config, &$errors)
    {
        if (empty($config['api_key'])) {
            $errors['api_key'] = 'API Key is required.';
        } elseif (strlen($config['api_key']) < 20) {
            $errors['api_key'] = 'API Key must be at least 20 characters long.';
        }

        if (empty($config['model'])) {
            $errors['model'] = 'Model selection is required.';
        }

        // success when no errors
        return !$errors;
    }
}
