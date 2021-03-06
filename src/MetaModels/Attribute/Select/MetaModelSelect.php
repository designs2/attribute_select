<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package    MetaModels
 * @subpackage AttributeSelect
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan heimes <stefan_heimes@hotmail.com>
 * @author     Martin Treml <github@r2pi.net>
 * @author     David Maack <david.maack@arcor.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Select;

use MetaModels\Filter\IFilter;
use MetaModels\Filter\Rules\StaticIdList;
use MetaModels\IItem;
use MetaModels\IItems;
use MetaModels\IMetaModel;
use MetaModels\Render\Template;

/**
 * This is the MetaModelAttribute class for handling select attributes on MetaModels.
 */
class MetaModelSelect extends AbstractSelect
{
    /**
     * The key in the result array where the RAW values shall be stored.
     */
    const SELECT_RAW = '__SELECT_RAW__';

    /**
     * The MetaModel we are referencing on.
     *
     * @var IMetaModel
     */
    protected $objSelectMetaModel;

    /**
     * {@inheritDoc}
     */
    protected function checkConfiguration()
    {
        return parent::checkConfiguration()
            && (null !== $this->getSelectMetaModel());
    }

    /**
     * Retrieve the linked MetaModel instance.
     *
     * @return IMetaModel
     */
    protected function getSelectMetaModel()
    {
        if (empty($this->objSelectMetaModel)) {
            $this->objSelectMetaModel = $this
                ->getMetaModel()
                ->getServiceContainer()
                ->getFactory()
                ->getMetaModel($this->getSelectSource());
        }

        return $this->objSelectMetaModel;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareTemplate(Template $objTemplate, $arrRowData, $objSettings)
    {
        parent::prepareTemplate($objTemplate, $arrRowData, $objSettings);
        /** @noinspection PhpUndefinedFieldInspection */
        $objTemplate->displayValue = $this->getValueColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeSettingNames()
    {
        return array_merge(
            parent::getAttributeSettingNames(),
            array(
                'select_filter',
                'select_filterparams',
            )
        );
    }

    /**
     * Convert the item list to values.
     *
     * @param IItems $items The items to convert.
     *
     * @return array
     */
    protected function itemsToValues(IItems $items)
    {
        $values = array();
        foreach ($items as $item) {
            $valueId    = $item->get('id');
            $parsedItem = $item->parseValue();

            $values[$valueId] = array_merge(
                array(self::SELECT_RAW => $parsedItem['raw']),
                $parsedItem['text']
            );
        }

        return $values;
    }

    /**
     * Retrieve the values with the given ids.
     *
     * @param string[] $valueIds The ids of the values to retrieve.
     *
     * @return array
     */
    protected function getValuesById($valueIds)
    {
        $recursionKey = $this->getMetaModel()->getTableName();

        // Prevent recursion.
        static $tables = array();
        if (isset($tables[$recursionKey])) {
            return array();
        }
        $tables[$recursionKey] = $recursionKey;

        $metaModel = $this->getSelectMetaModel();
        $filter    = $metaModel->getEmptyFilter()->addFilterRule(new StaticIdList($valueIds));
        $items     = $metaModel->findByFilter($filter, 'id');
        unset($tables[$recursionKey]);

        return $this->itemsToValues($items);
    }

    /**
     * {@inheritdoc}
     */
    public function valueToWidget($varValue)
    {
        if (isset($varValue[$this->getAliasColumn()])) {
            // Hope the best that this is unique...
            return (string) $varValue[$this->getAliasColumn()];
        }

        if (isset($varValue[self::SELECT_RAW]['id'])) {
            return (string) $varValue[self::SELECT_RAW]['id'];
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException when the value is invalid.
     */
    public function widgetToValue($varValue, $itemId)
    {
        $model     = $this->getSelectMetaModel();
        $alias     = $this->getAliasColumn();
        $attribute = $model->getAttribute($alias);

        if ($attribute) {
            // It is an attribute, we may search for it.
            $ids = $attribute->searchFor($varValue);
            if (!$ids) {
                $valueId = 0;
            } else {
                if (count($ids) > 1) {
                    throw new \RuntimeException(
                        sprintf(
                            'Multiple values found for %s, are there obsolete values for %s.%s (att_id: %s)?',
                            var_export($varValue, true),
                            $model->getTableName(),
                            $this->getColName(),
                            $this->get('id')
                        )
                    );
                }
                $valueId = array_shift($ids);
            }
        } else {
            // Must be a system column then.
            // Special case first, the id is our alias, easy way out.
            if ($alias === 'id') {
                $valueId = $varValue;
            } else {
                $result = $this->getDatabase()
                    ->prepare(
                        sprintf(
                            'SELECT v.id FROM %1$s AS v WHERE v.%2$s=?',
                            $this->getSelectSource(),
                            $this->getAliasColumn()
                        )
                    )
                    ->execute($varValue);

                /** @noinspection PhpUndefinedFieldInspection */
                if (!$result->numRows) {
                    throw new \RuntimeException('Could not translate value ' . var_export($varValue, true));
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $valueId = $result->id;
            }
        }

        $value = $this->getValuesById(array($valueId));

        return $value[$valueId];
    }

    /**
     * Fetch filter options from foreign table taking the given flag into account.
     *
     * @param IFilter $filter The filter to which the rules shall be added to.
     *
     * @param array   $idList The list of ids of items for which the rules shall be added.
     *
     * @return void
     */
    public function buildFilterRulesForUsedOnly($filter, $idList = array())
    {
        if (empty($idList)) {
            $query = sprintf(
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                'SELECT %2$s FROM %1$s GROUP BY %2$s',
                $this->getMetaModel()->getTableName(), // 1
                $this->getColName()                    // 2
            // @codingStandardsIgnoreEnd
            );
        } else {
            $query = sprintf(
            // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
                'SELECT %2$s FROM %1$s WHERE id IN (%3$s) GROUP BY %2$s',
                $this->getMetaModel()->getTableName(), // 1
                $this->getColName(),                   // 2
                $this->parameterMask($idList)          // 3
            // @codingStandardsIgnoreEnd
            );
        }

        $arrUsedValues = $this->getDatabase()
            ->prepare($query)
            ->execute($idList)
            ->fetchEach($this->getColName());

        $arrUsedValues = array_filter(
            $arrUsedValues,
            function ($value) {
                return !empty($value);
            }
        );

        $filter->addFilterRule(new StaticIdList($arrUsedValues));
    }

    /**
     * Fetch filter options from foreign table taking the given flag into account.
     *
     * @param IFilter $filter The filter to which the rules shall be added to.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function buildFilterRulesForFilterSetting($filter)
    {
        if (!$this->get('select_filter')) {
            return;
        }

        // Set Filter and co.
        $filterSettings = $this
            ->getMetaModel()
            ->getServiceContainer()
            ->getFilterFactory()
            ->createCollection($this->get('select_filter'));

        if ($filterSettings) {
            $values       = $_GET;
            $presets      = (array) $this->get('select_filterparams');
            $presetNames  = $filterSettings->getParameters();
            $filterParams = array_keys($filterSettings->getParameterFilterNames());
            $processed    = array();

            // We have to use all the preset values we want first.
            foreach ($presets as $presetName => $preset) {
                if (in_array($presetName, $presetNames)) {
                    $processed[$presetName] = $preset['value'];
                }
            }

            // Now we have to use all FrontEnd filter params, that are either:
            // * not contained within the presets
            // * or are overridable.
            foreach ($filterParams as $parameter) {
                // Unknown parameter? - next please.
                if (!array_key_exists($parameter, $values)) {
                    continue;
                }

                // Not a preset or allowed to override? - use value.
                if ((!array_key_exists($parameter, $presets)) || $presets[$parameter]['use_get']) {
                    $processed[$parameter] = $values[$parameter];
                }
            }

            $filterSettings->addRules($filter, $processed);
        }
    }

    /**
     * Convert a collection of items into a proper filter option list.
     *
     * @param IItems|IItem[] $items        The item collection to convert.
     *
     * @param string         $displayValue The name of the attribute to use as value.
     *
     * @param string         $aliasColumn  The name of the attribute to use as alias.
     *
     * @param null|string[]  $count        The counter array.
     *
     * @return array
     */
    protected function convertItemsToFilterOptions($items, $displayValue, $aliasColumn, &$count = null)
    {
        if (null !== $count) {
            $this->determineCount($items, $count);
        }

        $result = array();
        foreach ($items as $item) {
            $parsedDisplay = $item->parseAttribute($displayValue);
            $parsedAlias   = $item->parseAttribute($aliasColumn);

            $textValue  = isset($parsedDisplay['text'])
                ? $parsedDisplay['text']
                : $item->get($displayValue);
            $aliasValue = isset($parsedAlias['text'])
                ? $parsedAlias['text']
                : $item->get($aliasColumn);

            $result[$aliasValue] = $textValue;

            if (null !== $count) {
                if (isset($count[$item->get('id')])) {
                    $count[$aliasValue] = $count[$item->get('id')];
                    unset($count[$item->get('id')]);
                }
            }
        }

        return $result;
    }

    /**
     * Determine the option count for the passed items.
     *
     * @param IItems|IItem[] $items The item collection to convert.
     *
     * @param null|string[]  $count The counter array.
     *
     * @return void
     */
    private function determineCount($items, &$count)
    {
        $idList = array_unique(array_filter(array_map(
            function ($item) {
                /** @var IItem $item */
                return $item->get('id');
            },
            iterator_to_array($items)
        )));

        if (empty($idList)) {
            return;
        }

        $valueCol = $this->getColName();
        $query    = $this->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT %2$s, COUNT(%2$s) AS count FROM %1$s WHERE %2$s IN (%3$s) GROUP BY %2$s',
                    $this->getMetaModel()->getTableName(),
                    $this->getColName(),
                    $this->parameterMask($idList)
                )
            )
            ->execute($idList);

        while ($query->next()) {
            $count[$query->{$valueCol}] = $query->count;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Fetch filter options from foreign table.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function getFilterOptions($idList, $usedOnly, &$arrCount = null)
    {
        if (!$this->isFilterOptionRetrievingPossible($idList)) {
            return array();
        }

        $strDisplayValue    = $this->getValueColumn();
        $strSortingValue    = $this->getSortingColumn();
        $strCurrentLanguage = null;

        // Change language.
        if (TL_MODE == 'BE') {
            $strCurrentLanguage     = $GLOBALS['TL_LANGUAGE'];
            $GLOBALS['TL_LANGUAGE'] = $this->getMetaModel()->getActiveLanguage();
        }

        $filter = $this->getSelectMetaModel()->getEmptyFilter();

        $this->buildFilterRulesForFilterSetting($filter);

        // Add some more filter rules.
        if ($usedOnly) {
            $this->buildFilterRulesForUsedOnly($filter, $idList ?: array());

        } elseif ($idList && is_array($idList)) {
            $filter->addFilterRule(new StaticIdList($idList));
        }

        $objItems = $this->getSelectMetaModel()->findByFilter($filter, $strSortingValue);

        // Reset language.
        if (TL_MODE == 'BE') {
            $GLOBALS['TL_LANGUAGE'] = $strCurrentLanguage;
        }

        return $this->convertItemsToFilterOptions($objItems, $strDisplayValue, $this->getAliasColumn(), $arrCount);
    }

    /**
     * {@inheritdoc}
     *
     * This implementation does a complete sorting by the referenced MetaModel.
     */
    public function sortIds($idList, $strDirection)
    {
        $metaModel = $this->getSelectMetaModel();
        $myColName = $this->getColName();
        $values    = $this
            ->getMetaModel()
            ->getServiceContainer()
            ->getDatabase()
            ->prepare(
                sprintf(
                    'SELECT id,%1$s FROM %2$s WHERE id IN (%3$s) ORDER BY %1$s',
                    $myColName,
                    $this->getMetaModel()->getTableName(),
                    $this->parameterMask($idList)
                )
            )
            ->execute($idList);

        $valueIds = array();
        $valueMap = array();
        while ($values->next()) {
            $itemId             = $values->id;
            $value              = $values->$myColName;
            $valueIds[$itemId]  = $value;
            $valueMap[$value][] = $itemId;
        }

        $filter = $metaModel->getEmptyFilter()->addFilterRule(new StaticIdList(array_unique(array_values($valueIds))));
        $value  = $this->getValueColumn();
        $items  = $metaModel->findByFilter($filter, $value, 0, 0, $strDirection, array($value));
        $result = array();
        foreach ($items as $item) {
            $result = array_merge($result, $valueMap[$item->get('id')]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFor($arrIds)
    {
        if (!$this->isProperlyConfigured()) {
            return array();
        }

        $result      = array();
        $valueColumn = $this->getColName();
        // First pass, load database rows.
        $rows = $this->getDatabase()->prepare(
            sprintf(
                'SELECT %2$s, id FROM %1$s WHERE id IN (%3$s)',
                // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following
                // lines.
                $this->getMetaModel()->getTableName(), // 1
                $valueColumn,                          // 2
                $this->parameterMask($arrIds)          // 3
            // @codingStandardsIgnoreEnd
            )
        )->execute($arrIds);

        $valueIds = array();
        while ($rows->next()) {
            /** @noinspection PhpUndefinedFieldInspection */
            $valueIds[$rows->id] = $rows->$valueColumn;
        }

        $values = $this->getValuesById($valueIds);

        foreach ($valueIds as $itemId => $valueId) {
            if (empty($valueId)) {
                $result[$itemId] = null;
                continue;
            }
            $result[$itemId] = $values[$valueId];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException when invalid data is encountered.
     */
    public function setDataFor($arrValues)
    {
        if (!($this->getSelectSource() && $this->getValueColumn())) {
            return;
        }

        $query = sprintf(
        // @codingStandardsIgnoreStart - We want to keep the numbers as comment at the end of the following lines.
            'UPDATE %1$s SET %2$s=? WHERE %1$s.id=?',
            $this->getMetaModel()->getTableName(), // 1
            $this->getColName()                    // 2
        // @codingStandardsIgnoreEnd
        );

        $database = $this->getDatabase();
        foreach ($arrValues as $itemId => $value) {
            if (is_array($value) && isset($value[self::SELECT_RAW]['id'])) {
                $database->prepare($query)->execute($value[self::SELECT_RAW]['id'], $itemId);
            } elseif (is_numeric($itemId) && (is_numeric($value) || $value === null)) {
                $database->prepare($query)->execute($value, $itemId);
            } else {
                throw new \RuntimeException(
                    'Invalid values encountered, itemId: ' .
                    var_export($value, true) .
                    ' value: ' . var_export($value, true)
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convertValuesToValueIds($values)
    {
        $strColNameAlias = $this->getAliasColumn();
        $strColNameId    = $this->getIdColumn();

        if ($strColNameId === $strColNameAlias) {
            return $values;
        }

        $attribute = $this->getSelectMetaModel()->getAttribute($strColNameAlias);
        if (!$attribute) {
            // If not an attribute, perform plain SQL translation. See #32, 34.
            return parent::convertValuesToValueIds($values);
        }

        $sanitizedValues = array();
        foreach ($values as $value) {
            $valueIds = $attribute->searchFor($value);
            if ($valueIds === null) {
                return null;
            }

            $sanitizedValues = array_merge($valueIds, $sanitizedValues);
        }

        return array_unique($sanitizedValues);
    }
}
