<?php

namespace Fromholdio\CMSFieldsPlacement;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;

class CMSFieldsPlacement
{
    use Configurable;
    use Extensible;
    use Injectable;

    public static function placeFields(
        FieldList $fields,
        FieldList $newFields,
        ?array $config,
        ?DataObject $dataObject = null
    ): FieldList
    {
        if (empty($config)) {
            return $fields;
        }
        foreach ($newFields as $field) {
            $fields = static::placeField(
                $fields, $field, $config, $dataObject
            );
        }
        return $fields;
    }

    public static function placeField(
        FieldList $fields,
        $field,
        ?array $config,
        ?DataObject $dataObject = null
    ): FieldList
    {
        if (empty($config)) {
            return $fields;
        }

        if (!is_a($field, FormField::class) && !is_a($field, FieldList::class)) {
            return $fields;
        }

        $targetFields = $fields;
        $tabPath = $config['tab_path'] ?? null;
        if (!empty($tabPath))
        {
            $tabFields = $fields;
            $tabPathParts = explode('.', $tabPath);
            $tabPathTotalSteps = count($tabPathParts);
            $tabPathCurrStep = 1;
            foreach ($tabPathParts as $tabPathPart)
            {
                $isLastStep = $tabPathTotalSteps === $tabPathCurrStep;
                $tabTitle = is_null($dataObject) ? null : $dataObject->fieldLabel($tabPathPart);
                $tab = $tabFields->fieldByName($tabPathPart);
                if ($isLastStep) {
                    if ($tab && !is_a($tab, Tab::class)) {
                        throw new \UnexpectedValueException(
                            'Tab path part "' . $tabPathPart . '" exists but is '
                            . 'not a Tab as required. Instead it is "' . get_class($tab) . '".'
                        );
                    }
                    if (!$tab) {
                        $tab = Tab::create($tabPathPart);
                        $tabFields->push($tab);
                    }
                    if (!empty($tabTitle)) {
                        $tab->setTitle($tabTitle);
                    }
                    $targetFields = $tab->Fields();
                    break;
                }

                if ($tab && !is_a($tab, TabSet::class)) {
                    throw new \UnexpectedValueException(
                        'Tab path part "' . $tabPathPart . '" exists but is '
                        . 'not a TabSet as required. Instead it is "' . get_class($tab) . '".'
                    );
                }

                if (!$tab) {
                    $tab = TabSet::create($tabPathPart);
                    $tabFields->push($tab);
                }
                if (!empty($tabTitle)) {
                    $tab->setTitle($tabTitle);
                }
                $tabFields = $tab->FieldList();
                $tabPathCurrStep++;
            }
        }

        if (is_a($field, FieldList::class)) {
            foreach ($field as $singleField) {
                static::insertField($targetFields, $singleField, $config);
            }
        }
        else {
            static::insertField($targetFields, $field, $config);
        }
        return $fields;
    }

    public static function insertField(FieldList $fields, FormField $field, ?array $config): FieldList
    {
        $insertBefore = $config['insert_before'] ?? null;
        $insertAfter = $config['insert_after'] ?? null;
        if (!empty($insertBefore)) {
            $fields->insertBefore($insertBefore, $field);
        }
        elseif (!empty($insertAfter)) {
            $fields->insertAfter($insertAfter, $field);
        }
        else {
            $fields->push($field);
        }
        return $fields;
    }

    public static function convertTabToTabSet(Tab $tab, ?DataObject $dataObject = null): TabSet
    {
        $tabSetName = $tab->getName() . 'TabSet';
        $tabSetTitle = is_null($dataObject)
            ? $tab->Title()
            : $dataObject->fieldLabel($tabSetName);

        $newTab = Tab::create(
            $tab->getName(),
            $tab->Title()
        );
        foreach ($tab->Fields() as $field) {
            $newTab->push($field);
        }

        return TabSet::create($tabSetName, $tabSetTitle, $newTab);
    }

    public static function isInSection(string $type, ?array $config): ?string
    {
        $configType = $config['section'] ?? null;
        return $type === $configType;
    }
}
