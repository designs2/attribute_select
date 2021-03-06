<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package     MetaModels
 * @subpackage  AttributeSelect
 * @author      Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author      Stefan Heimes <stefan_heimes@hotmail.com>
 * @copyright   The MetaModels team.
 * @license     LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Select;

use MetaModels\Attribute\AbstractHybrid;
use MetaModels\Filter\Rules\FilterRuleSelect;

/**
 * This is the abstract base class for handling select attributes.
 *
 * @package    MetaModels
 * @subpackage AttributeSelect
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
abstract class AbstractSelect extends AbstractHybrid
{
    /**
     * The widget mode to use.
     *
     * @var int
     */
    protected $widgetMode;

    /**
     * Local cached flag if the attribute has been properly configured.
     *
     * @var bool
     */
    private $isProperlyConfigured;

    /**
     * Retrieve the database instance.
     *
     * @return \Database
     */
    protected function getDatabase()
    {
        return $this->getMetaModel()->getServiceContainer()->getDatabase();
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDataType()
    {
        return 'int(11) NOT NULL default \'0\'';
    }

    /**
     * Determine if we want to use tree selection.
     *
     * @return bool
     */
    protected function isTreePicker()
    {
        return $this->widgetMode == 2;
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getSelectSource()
    {
        return $this->get('select_table');
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getIdColumn()
    {
        return $this->get('select_id') ?: 'id';
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getSortingColumn()
    {
        return $this->get('select_sorting') ?: $this->getIdColumn();
    }

    /**
     * Determine the correct sorting column to use.
     *
     * @return string
     */
    protected function getValueColumn()
    {
        return $this->get('select_column');
    }

    /**
     * Determine the correct alias column to use.
     *
     * @return string
     */
    protected function getAliasColumn()
    {
        return $this->get('select_alias') ?: $this->getIdColumn();
    }

    /**
     * Ensure the attribute has been configured correctly.
     *
     * @return bool
     */
    protected function isProperlyConfigured()
    {
        if (isset($this->isProperlyConfigured)) {
            return $this->isProperlyConfigured;
        }

        return $this->isProperlyConfigured = $this->checkConfiguration();
    }

    /**
     * Check the configuration of the attribute.
     *
     * @return bool
     */
    protected function checkConfiguration()
    {
        return $this->getSelectSource()
            && $this->getValueColumn()
            && $this->getAliasColumn()
            && $this->getIdColumn()
            && $this->getSortingColumn();
    }

    /**
     * Test that we can create the filter options.
     *
     * @param string[]|null $idList The ids of items that the values shall be fetched from
     *                              (If empty or null, all items).
     *
     * @return bool
     */
    protected function isFilterOptionRetrievingPossible($idList)
    {
        return $this->isProperlyConfigured() && (($idList === null) || !empty($idList));
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinition($arrOverrides = array())
    {
        $arrFieldDef      = parent::getFieldDefinition($arrOverrides);
        $this->widgetMode = $arrOverrides['select_as_radio'];
        if ($this->isTreePicker()) {
            $arrFieldDef['inputType']          = 'DcGeneralTreePicker';
            $arrFieldDef['eval']['sourceName'] = $this->getSelectSource();
            $arrFieldDef['eval']['fieldType']  = 'radio';
            $arrFieldDef['eval']['idProperty'] = $this->getAliasColumn();
            $arrFieldDef['eval']['orderField'] = $this->getSortingColumn();
            $arrFieldDef['eval']['minLevel']   = $arrOverrides['select_minLevel'];
            $arrFieldDef['eval']['maxLevel']   = $arrOverrides['select_maxLevel'];
        } elseif ($this->widgetMode == 1) {
            // If select as radio is true, change the input type.
            $arrFieldDef['inputType'] = 'radio';
        } else {
            $arrFieldDef['inputType'] = 'select';
        }

        return $arrFieldDef;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeSettingNames()
    {
        return array_merge(
            parent::getAttributeSettingNames(),
            array(
                'select_table',
                'select_column',
                'select_alias',
                'select_sorting',
                'select_as_radio',
                'includeBlankOption',
                'submitOnChange',
                'mandatory',
                'chosen',
                'filterable',
                'searchable'
            )
        );
    }

    /**
     * {@inheritdoc}
     *
     * search value in table
     */
    public function searchFor($strPattern)
    {
        $objFilterRule = new FilterRuleSelect($this, $strPattern);

        return $objFilterRule->getMatchingIds();
    }

    /**
     * {@inheritdoc}
     */
    public function unsetDataFor($arrIds)
    {
        $strQuery = sprintf(
            'UPDATE %1$s SET %2$s=0 WHERE %1$s.id IN (%3$s)',
            $this->getMetaModel()->getTableName(),
            $this->getColName(),
            implode(',', $arrIds)
        );
        $this->getDatabase()->execute($strQuery);
    }

    /**
     * Convert the passed values to a list of value ids.
     *
     * @param string[] $values The values to convert.
     *
     * @return int[]
     */
    public function convertValuesToValueIds($values)
    {
        $tableName   = $this->getSelectSource();
        $idColumn    = $this->getIdColumn();
        $aliasColumn = $this->getAliasColumn();

        if ($idColumn === $aliasColumn) {
            return $values;
        }

        $values = array_unique(array_filter($values));
        if (empty($values)) {
            return array();
        }
        $objSelectIds = $this->getDatabase()
            ->prepare(sprintf(
                'SELECT %s FROM %s WHERE %s IN (%s)',
                $idColumn,
                $tableName,
                $aliasColumn,
                $this->parameterMask($values)
            ))
            ->execute($values);

        return $objSelectIds->fetchEach($idColumn);
    }
}
