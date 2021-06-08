<?php

namespace Daalder\JobCentral\Models;

use Illuminate\Database\Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Lorisleiva\LaravelSearchString\SearchStringManager;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\DateHistogramAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\HistogramAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\AvgAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MaxAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MinAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;

use Pionect\Daalder\Events\Product\AggregationBuilder;
use Pionect\Daalder\Services\Cache\CacheRepository;
use Pionect\Daalder\Services\CustomerToken\CustomerTokenResolver;
use Pionect\Daalder\Services\Elastic\BucketSortAggregation;
use Pionect\Daalder\Services\Elastic\ElasticStringQuery;
use Pionect\Daalder\Services\Search\ElasticSearchQueryBuilder;
use Pionect\Daalder\Services\Search\SortParamsParser;
use Pionect\Daalder\Models\Category\Category;

use Pionect\Daalder\Models\Store\Store;
use Pionect\Daalder\Models\Upsell\Upsell;

class JCJobFetcher
{
    /**
     * @var SortParamsParser
     */
    protected $sortParamsParser;

    /**
     * @var array
     */
    private $filterAliases = [];

    /**
     * @var ElasticStringQuery
     */
    private $elasticStringQuery;

    /**
     * @var CacheRepository
     */
    private $cacheRepository;

    /**
     * @param  SortParamsParser  $sortParamsParser
     * @param  ElasticStringQuery  $elasticStringQuery
     * @param  CacheRepository  $cacheRepository
     */
    public function __construct(
        SortParamsParser $sortParamsParser,
        ElasticStringQuery $elasticStringQuery,
        CacheRepository $cacheRepository
    ) {
        $this->sortParamsParser = $sortParamsParser;
        $this->elasticStringQuery = $elasticStringQuery;
        $this->cacheRepository = $cacheRepository;
        $this->cacheRepository->setDefaultTTL(5); // 5 minutes
    }

    /**
     * @param  array  $params
     * @return ProductCollection
     * @throws \Exception
     */
    public function search(array $params = [])
    {
        $results = $this->cacheRepository->remember('jcjob-search', function () use ($params) {
            $esBuilder = $this->makeFilterBuilder($params);
            $search = $esBuilder->getSearch();

            $aggregations = $this->buildAggregations($params);

            foreach($aggregations as $aggregation) {
                $search->addAggregation($aggregation);
            }

            $result = $esBuilder->getResult();
            $aggregations = $esBuilder->getAggregations($result);
            $hits = $esBuilder->getHits($result);

            return (object) [
                'aggregations' => $aggregations,
                'hits' => $hits,
                'total' => $hits['total'],
            ];

        }, $params);

        return $results;
    }

    /**
     * @param  array  $params
     * @param  string  $eventClass
     * @return ElasticSearchQueryBuilder
     */
    public function makeFilterBuilder(array $params = [], $eventClass = null)
    {
        $esBuilder = new ElasticSearchQueryBuilder(JCJob::class, $eventClass);

        $params = collect($params);

        $esBuilder->paginateFromParams($params);
//        $esBuilder = $this->applySource($esBuilder, $params);

        if (is_array(array_get($params, 'filter'))) {
            $filters = $this->getFilters($params);
            $esBuilder->filter($filters);
        } else {
            $this->makeSearch(array_get($params, 'filter'), $esBuilder);
        }

        return $esBuilder;
    }

    /**
     * @param $params
     * @return array
     */
    private function getFilters($params)
    {
        $paramFilters = $params->get('filter', []);
        $filters = [];

        foreach ($paramFilters as $field => $value) {
            if (array_has($this->filterAliases, $field)) {
                $filters[$this->filterAliases[$field]] = $value;
            } else {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param $filter
     * @param       $esBuilder
     */
    protected function makeSearch($filter, ElasticSearchQueryBuilder $esBuilder)
    {
        if (!empty($filter)) {
            $searchStringManager = new SearchStringManager(new JCJob);
            $ast = $searchStringManager->parse($filter);
            $query = $this->elasticStringQuery->parse($ast);
            $search = $esBuilder->getSearch();
            $search->addQuery($query);
        }
    }

    /**
     * @param  array  $params
     * @param  null  $category
     * @return array $aggregrations
     */
    public function buildAggregations($params = [])
    {
        $aggregations = [];

        if(!array_has($params, 'filter') && !array_has($params['filter'], 'finished_or_failed_at')) {
            return [];
        }

        $dateMin = Carbon::parse($params['filter']['finished_or_failed_at']['min']);
        $dateMax = Carbon::parse($params['filter']['finished_or_failed_at']['max']);

        $hourlyAggregation = new DateHistogramAggregation(
        'histogram_hourly',
        'finished_or_failed_at',
        '1h',
        'yyyy-MM-dd H:m:s',
        );

        $hourlyAggregation->addParameter('extended_bounds', [
            'min' => $dateMin->format('Y-m-d H:m:s'),
            'max' => $dateMax->format('Y-m-d H:m:s')
        ]);

        $dailyAggregation = new DateHistogramAggregation(
            'histogram_daily',
            'finished_or_failed_at',
            '1d',
            'yyyy-MM-dd'
        );

        $dailyAggregation->addParameter('extended_bounds', [
            'min' => $dateMin->format('Y-m-d'),
            'max' => $dateMax->format('Y-m-d')
        ]);

        array_push($aggregations, $hourlyAggregation);
        array_push($aggregations, $dailyAggregation);

        foreach($aggregations as $key => $aggregation) {
            $aggregationBuilder = new AggregationBuilder($aggregation);
            event($aggregationBuilder);
            $aggregations[$key] = $aggregationBuilder->getMainAggregation();
        }

        return $aggregations;
    }
}
