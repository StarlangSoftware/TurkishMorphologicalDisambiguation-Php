<?php

namespace olcaytaner\MorphologicalDisambiguation;

use olcaytaner\MorphologicalAnalysis\Corpus\DisambiguationCorpus;

abstract class MorphologicalDisambiguator
{
    /**
     * Method to train the given {@link DisambiguationCorpus}.
     *
     * @param $corpus {@link DisambiguationCorpus} to train.
     */
    public abstract function train(DisambiguationCorpus $corpus): void;

    /**
     * Method to disambiguate the given {@link FsmParseList}.
     *
     * @param array $fsmParses {@link FsmParseList} to disambiguate.
     * @return array array of {@link FsmParse}.
     */
    public abstract function disambiguate(array $fsmParses): array;
}