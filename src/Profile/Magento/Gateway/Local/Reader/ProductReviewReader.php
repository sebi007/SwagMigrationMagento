<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ProductReviewReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT_REVIEW;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedProductReviews = $this->mapData($this->fetchProductReviews($migrationContext), [], ['detail']);
        $ids = array_column($fetchedProductReviews, 'review_id');
        $fetchedRatings = $this->mapData($this->fetchRatings($ids), [], ['opt']);
        $defaultLocale = str_replace('_', '-', $this->fetchDefaultLocale());

        foreach ($fetchedProductReviews as &$productReview) {
            $review_id = $productReview['review_id'];

            if (isset($fetchedRatings[$review_id])) {
                $productReview['ratings'] = $fetchedRatings[$review_id];
            }

            if (isset($productReview['locale'])) {
                $productReview['locale'] = str_replace('_', '-', $productReview['locale']);
            } else {
                $productReview['locale'] = $defaultLocale;
            }
        }

        return $this->utf8ize($fetchedProductReviews);
    }

    protected function fetchProductReviews(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'review', 'review');

        $query->innerJoin('review', $this->tablePrefix . 'review_entity', 'entity', 'review.entity_id = entity.entity_id AND entity.entity_code = \'product\'');
        $query->addSelect('review.entity_pk_value AS `detail.productId`');

        $query->innerJoin('review', $this->tablePrefix . 'review_status', 'status', 'status.status_id = review.status_id');
        $query->addSelect('status.status_code AS `detail.status`');

        $query->innerJoin('review', $this->tablePrefix . 'review_detail', 'detail', 'detail.review_id = review.review_id');
        $this->addTableSelection($query, $this->tablePrefix . 'review_detail', 'detail');

        $query->leftJoin(
            'detail',
            $this->tablePrefix . 'core_config_data',
            'locale',
            'locale.scope_id = detail.store_id AND locale.scope = \'stores\' AND path = \'general/locale/code\''
        );
        $query->addSelect('locale.value AS `detail.locale`');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function fetchRatings(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'rating_option_vote', 'opt');
        $query->addSelect('opt.review_id AS identifier');
        $this->addTableSelection($query, $this->tablePrefix . 'rating_option_vote', 'opt');

        $query->innerJoin('opt', $this->tablePrefix . 'rating', 'rating', 'rating.rating_id = opt.rating_id');
        $query->addSelect('rating.rating_code AS `opt.rating_code`');

        $query->andWhere('opt.review_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
