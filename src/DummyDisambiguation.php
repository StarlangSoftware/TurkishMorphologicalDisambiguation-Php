<?php

namespace olcaytaner\MorphologicalDisambiguation;

use olcaytaner\MorphologicalAnalysis\Corpus\DisambiguationCorpus;
use olcaytaner\MorphologicalAnalysis\MorphologicalAnalysis\FsmParseList;

class DummyDisambiguation extends MorphologicalDisambiguator
{

    /**
     * Train method implements method in {@link MorphologicalDisambiguator}.
     *
     * @param DisambiguationCorpus $corpus {@link DisambiguationCorpus} to train.
     */
    public function train(DisambiguationCorpus $corpus): void
    {
    }

    /**
     * Overridden disambiguate method takes an array of {@link FsmParseList} and loops through its items, if the current FsmParseList's
     * size is greater than 0, it adds a random parse of this list to the correctFsmParses {@link ArrayList}.
     *
     * @param array $fsmParses {@link FsmParseList} to disambiguate.
     * @return array correctFsmParses {@link ArrayList}.
     */
    public function disambiguate(array $fsmParses): array
    {
        $correctFsmParses = [];
        foreach ($fsmParses as $fsmParseList) {
            if ($fsmParseList instanceof FsmParseList && $fsmParseList->size() > 0){
                $correctFsmParses[] = $fsmParseList->getFsmParse(rand(0, $fsmParseList->size() - 1));
            }
        }
        return $correctFsmParses;
    }
}