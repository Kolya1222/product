<?php 

namespace roilafx\Product\Helpers;

use Symfony\Component\Finder\Finder;

class TvTypeHelper
{
    public static function forSelect(): array
    {
        $standard = [
            'text'             => 'Text',
            'textarea'         => 'Textarea',
            'textareamini'     => 'Textarea (Mini)',
            'richtext'         => 'RichText',
            'dropdown'         => 'DropDown List Menu',
            'listbox'          => 'Listbox (Single-Select)',
            'listbox-multiple' => 'Listbox (Multi-Select)',
            'option'           => 'Radio Options',
            'checkbox'         => 'Check Box',
            'image'            => 'Image',
            'file'             => 'File',
            'url'              => 'URL',
            'email'            => 'Email',
            'number'           => 'Number',
            'date'             => 'Date'
        ];

        $custom = ['custom_tv' => 'Custom Input'];

        $path = MODX_BASE_PATH . 'assets/tvs/';
        if (is_dir($path)) {
            $finder = Finder::create()
                ->in($path)->depth(0)
                ->notName('/^index\.html$/')
                ->sortByName();
            foreach ($finder as $dir) {
                $name = $dir->getFilename();
                $custom['custom_tv:' . $name] = $name;
            }
        }

        return [
            0 => ['optgroup' => ['name' => 'Standard Type', 'options' => $standard]],
            1 => ['optgroup' => ['name' => 'Custom Type',  'options' => $custom]],
        ];
    }
}