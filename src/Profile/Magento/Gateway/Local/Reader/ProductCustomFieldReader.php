<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ProductCustomFieldReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT_CUSTOM_FIELD;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $customFields = $this->fetchCustomFields($migrationContext);
        $ids = array_column($customFields, 'attribute_id');
        $options = $this->fetchSelectOptions($ids);

        foreach ($customFields as &$customField) {
            $attributeId = $customField['attribute_id'];
            $customField['defaultLocale'] = str_replace('_', '-', $customField['defaultLocale']);

            if (isset($options[$attributeId])) {
                $customField['options'] = $options[$attributeId];
            }
        }

        return $customFields;
    }

    protected function fetchCustomFields(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'eav_attribute', 'eav');
        $this->addTableSelection($query, $this->tablePrefix . 'eav_attribute', 'eav');
        $query->innerJoin('eav', $this->tablePrefix . 'eav_entity_type', 'et', 'eav.entity_type_id = et.entity_type_id AND et.entity_type_code = \'catalog_product\'');

        $query->innerJoin('eav', $this->tablePrefix . 'core_config_data', 'defaultLocale', 'defaultLocale.scope = \'default\' AND defaultLocale.path = \'general/locale/code\'');
        $query->addSelect('defaultLocale.value AS `eav.defaultLocale`');

        $query->innerJoin('eav', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 0');

        $query->where('eav.frontend_input != \'\'');
        $query->andWhere('eav.is_user_defined = 1');
        $query->andWhere('eav.attribute_code NOT IN (\'manufacturer\', \'cost\')');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $this->mapData($query->execute()->fetchAll(\PDO::FETCH_ASSOC), [], ['eav']);
    }

    protected function fetchSelectOptions(array $ids): array
    {
        $sql = <<<SQL
SELECT DISTINCT
    attribute.attribute_id,
    optionValue.option_id,
    optionValue.value
FROM {$this->tablePrefix}eav_attribute_option_value optionValue
INNER JOIN {$this->tablePrefix}eav_attribute_option attributeOption ON optionValue.option_id = attributeOption.option_id
INNER JOIN {$this->tablePrefix}eav_attribute attribute ON attribute.attribute_id = attributeOption.attribute_id
WHERE attribute.attribute_id IN (?);
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
