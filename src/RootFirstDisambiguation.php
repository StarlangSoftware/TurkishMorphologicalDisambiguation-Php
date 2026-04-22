<?php

namespace olcaytaner\MorphologicalDisambiguation;

use olcaytaner\Dictionary\Dictionary\Word;
use olcaytaner\MorphologicalAnalysis\Corpus\DisambiguationCorpus;
use olcaytaner\MorphologicalAnalysis\MorphologicalAnalysis\FsmParse;
use olcaytaner\MorphologicalAnalysis\MorphologicalAnalysis\FsmParseList;
use olcaytaner\NGram\LaplaceSmoothing;
use olcaytaner\NGram\NGram;

class RootFirstDisambiguation extends NaiveDisambiguation
{
    protected NGram $wordBiGramModel;
    protected NGram $igBiGramModel;

    /**
     * The train method initially creates new NGrams; wordUniGramModel, wordBiGramModel, igUniGramModel, and igBiGramModel. It gets the
     * sentences from given corpus and gets each word as a DisambiguatedWord. Then, adds the word together with its part of speech
     * tags to the wordUniGramModel. It also gets the transition list of that word and adds it to the igUniGramModel.
     * <p>
     * If there exists a next word in the sentence, it adds the current and next {@link DisambiguatedWord} to the wordBiGramModel with
     * their part of speech tags. It also adds them to the igBiGramModel with their transition lists.
     * <p>
     * At the end, it calculates the NGram probabilities of both word and ig unigram models by using LaplaceSmoothing, and
     * both word and ig bigram models by using InterpolatedSmoothing.
     *
     * @param DisambiguationCorpus $corpus {@link DisambiguationCorpus} to train.
     */
    public function train(DisambiguationCorpus $corpus): void
    {
        $words1 = [new Word("")];
        $igs1 = [new Word("")];
        $words2 = [new Word(""), new Word("")];
        $igs2 = [new Word(""), new Word("")];
        $this->wordUniGramModel = new NGram([], 1);
        $this->wordBiGramModel = new NGram([], 2);
        $this->igUniGramModel = new NGram([], 1);
        $this->igBiGramModel = new NGram([], 2);
        for ($i = 0; $i < $corpus->sentenceCount(); $i++) {
            $sentence = $corpus->getSentence($i);
            for ($j = 0; $j < $sentence->wordCount(); $j++) {
                $word = $sentence->getWord($j);
                $words1[0] = $word->getParse()->getWordWithPos();
                $this->wordUniGramModel->addNGram($words1);
                $igs1[0] = new Word($word->getParse()->getTransitionList());
                $this->igUniGramModel->addNGram($igs1);
                if ($j + 1 < $sentence->wordCount()) {
                    $words2[0] = $words1[0];
                    $words2[1] = ($sentence->getWord($j + 1))->getParse()->getWordWithPos();
                    $this->wordBiGramModel->addNGram($words2);
                    $igs2[0] = $igs1[0];
                    $igs2[1] = new Word(($sentence->getWord($j + 1))->getParse()->getTransitionList());
                    $this->igBiGramModel->addNGram($igs2);
                }
            }
        }
        $this->wordUniGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
        $this->igUniGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
        $this->wordBiGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
        $this->igBiGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
    }

    /**
     * The getWordProbability method returns the probability of a word by using word bigram or unigram model.
     *
     * @param Word $word Word to find the probability.
     * @param array $correctFsmParses FsmParse of given word which will be used for getting part of speech tags.
     * @param int $index Index of FsmParse of which part of speech tag will be used to get the probability.
     * @return float The probability of the given word.
     */
    protected function getWordProbability(Word $word, array $correctFsmParses, int $index): float
    {
        if ($index != 0 && count($correctFsmParses) == $index) {
            return $this->wordBiGramModel->getProbability($correctFsmParses[$index - 1]->getWordWithPos(), $word);
        } else {
            return $this->wordUniGramModel->getProbability($word);
        }
    }

    /**
     * The getIgProbability method returns the probability of a word by using ig bigram or unigram model.
     *
     * @param Word $word Word to find the probability.
     * @param array $correctFsmParses FsmParse of given word which will be used for getting transition list.
     * @param int $index Index of FsmParse of which transition list will be used to get the probability.
     * @return float The probability of the given word.
     */
    protected function getIgProbability(Word $word, array $correctFsmParses, int $index): float
    {
        if ($index != 0 && count($correctFsmParses) == $index) {
            return $this->igBiGramModel->getProbability(
                new Word($correctFsmParses[$index - 1]->getTransitionList()),
                $word
            );
        } else {
            return $this->igUniGramModel->getProbability($word);
        }
    }

    /**
     * The getBestRootWord method takes a {@link FsmParseList} as an input and loops through the list. It gets each word with its
     * part of speech tags as a new {@link Word} word and its transition list as a {@link Word} ig. Then, finds their corresponding
     * probabilities. At the end returns the word with the highest probability.
     *
     * @param FsmParseList $fsmParseList {@link FsmParseList} is used to get the part of speech tags and transition lists of words.
     * @return Word The word with the highest probability.
     */
    protected function getBestRootWord(FsmParseList $fsmParseList): Word
    {
        $bestProbability = -1;
        $bestWord = null;
        for ($j = 0; $j < $fsmParseList->size(); $j++) {
            $word = $fsmParseList->getFsmParse($j)->getWordWithPos();
            $ig = new Word($fsmParseList->getFsmParse($j)->getFsmParseTransitionList());
            $wordProbability = $this->wordUniGramModel->getProbability($word);
            $igProbability = $this->igUniGramModel->getProbability($ig);
            $probability = $wordProbability * $igProbability;
            if ($probability > $bestProbability) {
                $bestWord = $word;
                $bestProbability = $probability;
            }
        }
        return $bestWord;
    }

    /**
     * The getParseWithBestIgProbability gets each {@link FsmParse}'s transition list as a {@link Word} ig. Then, finds the corresponding
     * probabilitt. At the end returns the parse with the highest ig probability.
     *
     * @param FsmParseList $fsmParseList {@link FsmParseList} is used to get the {@link FsmParse}.
     * @param array $correctFsmParses FsmParse is used to get the transition lists.
     * @param int $index Index of FsmParse of which transition list will be used to get the probability.
     * @return FsmParse|null The parse with the highest probability.
     */
    protected function getParseWithBestIgProbability(FsmParseList $fsmParseList, array $correctFsmParses, int $index): ?FsmParse
    {
        $bestProbability = -1;
        $bestParse = null;
        for ($j = 0; $j < $fsmParseList->size(); $j++) {
            $ig = new Word($fsmParseList->getFsmParse($j)->getFsmParseTransitionList());
            $igProbability = $this->getIgProbability($ig, $correctFsmParses, $index);
            if ($igProbability > $bestProbability) {
                $bestParse = $fsmParseList->getFsmParse($j);
                $bestProbability = $igProbability;
            }
        }
        return $bestParse;
    }

    /**
     * The disambiguate method gets an array of fsmParses. Then loops through that parses and finds the most probable root
     * word and removes the other words which are identical to the most probable root word. At the end, gets the most probable parse
     * among the fsmParses and adds it to the correctFsmParses {@link ArrayList}.
     *
     * @param array $fsmParses {@link FsmParseList} to disambiguate.
     * @return array $correctFsmParses {@link ArrayList} which holds the most probable parses.
     */
    public function disambiguate(array $fsmParses): array{
        $correctFsmParses = [];
        for ($i = 0; $i < count($fsmParses); $i++) {
            $fsmParseList = $fsmParses[$i];
            if ($fsmParseList instanceof FsmParseList){
                $bestWord = $this->getBestRootWord($fsmParseList);
                $fsmParseList->reduceToParsesWithSameRootAndPos($bestWord);
                $bestParse = $this->getParseWithBestIgProbability($fsmParseList, $correctFsmParses, $i);
                if ($bestParse != null){
                    $correctFsmParses[] = $bestParse;
                }
            }
        }
        return $correctFsmParses;
    }
}