<?php

declare(strict_types=1);

namespace MDClub\Transformer;

/**
 * 回答转换器
 *
 * @property-read \MDClub\Model\Answer $answerModel
 */
class Answer extends Abstracts
{
    protected $table = 'answer';
    protected $primaryKey = 'answer_id';
    protected $availableIncludes = ['user', 'question', 'voting'];
    protected $userExcept = ['delete_time'];

    /**
     * 获取 answer 子资源
     *
     * @param  array $answerIds
     * @return array
     */
    public function getInRelationship(array $answerIds): array
    {
        if (!$answerIds) {
            return [];
        }

        $answers = $this->answerModel
            ->field(['answer_id', 'content_rendered', 'create_time', 'update_time'])
            ->select($answerIds);

        return collect($answers)
            ->keyBy('answer_id')
            ->map(function ($item) {
                $item['content_summary'] = mb_substr(strip_tags($item['content_rendered']), 0, 80);

                return $item;
            })
            ->unionFill($answerIds)
            ->all();
    }
}