<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CountryReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::COUNTRY;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $locales = $this->fetchLocales();

        $countries = [];
        foreach ($locales as $locale) {
            $countries[]['isoCode'] = $locale;
        }

        return $countries;
    }

    private function fetchLocales(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'currency');
        $query->addSelect('scope_id as store_id');
        $query->addSelect('value');
        $query->andwhere('path = \'general/country/allow\'');

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $countryConfig = [];
        foreach ($configurations as $key => $config) {
            if (isset($config['value'])) {
                $countryConfig = array_merge($countryConfig, explode(',', $config['value']));
            }
        }

        return array_values(array_unique($countryConfig));
    }
}
