<?php


// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 *
 * @package    course
 * @subpackage format_ludimoodle
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     celine <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_ludimoodle;

class relative_progress_position_calculator{
    /**
     * param $nbquestions number of questions in the quiz
     * param $nbcompetitors number of competing entities
     * param $answerscores array of answer score (0 for an incorrect answer, 1 for a correct answer, between the 2 for a partially correct answer)
     *
     **/
    function calculate($nbquestions, $nbcompetitors, $answerscores){
        // Start by calculating factors that will be used for determining proportion of ai population that gets each answer right vs wrong
        // The 'good' values correspond to the AI behaviour when the user has a correct answer, the 'bad' values are for tha AI when the user has a wrong answer
        $goodaitarget = 10;
        $badaitarget = 5;
        $nbquestionssub1 = $nbquestions-1 === 0 ? 1 : $nbquestions-1;
        $goodfactor = pow($goodaitarget / $nbcompetitors, 1 / ($nbquestionssub1));
        $badfactor  = 1 - pow($badaitarget / $nbcompetitors, 1 / ($nbquestionssub1));

        // iterate over user answers updating the histogram of AI population grades as required
        $scorefrequencies = [ $nbcompetitors ];
        $ownpoints = 0;
        $nbanswers = count($answerscores);
        for ($step = 0; $step < $nbanswers; ++$step){
            // prepare for new iteration
            $carry      = 0;
            $ownpoints  += $answerscores[$step];

            // determine the factor for proportion of AIs to have got the question right this time
            $factor = $badfactor + $answerscores[$step] * ($goodfactor - $badfactor);
            $nbscores = count($scorefrequencies);
            for($score = 0; $score < $nbscores; ++$score){
                # generate a biased factor, mixing the global factor (based on user performance) with a component based on a 'good people do well' concept
                $scoreweight = ($score + 1) / ($step + 1);
                $scorefactor = $badfactor + $scoreweight * ($goodfactor - $badfactor);
                $mixweight   = (2 - $step / $nbquestions) / 2;
                $mixedfactor = $mixweight * $factor + (1 - $mixweight) * $scorefactor;

                # calculate the number of bots who previously had this score and who have another correct answer and so will get 1 more point
                $oldcount  = $scorefrequencies[$score];
                $nbcorrect = ($score + 1 < $nbscores / 2)? floor($oldcount * $mixedfactor):  ceil($oldcount * $mixedfactor);

                # update the number of bots on this score, adding in bots with lower score who just moved up and taking out bots who have a correct answer and will be moving away
                $newcount  = $oldcount + $carry - $nbcorrect;
                $scorefrequencies[$score] = $newcount;

                # update the 'carry' variable to hold the number of bots with correct answers who are progressing up to the next score value
                $carry     = $nbcorrect;
            }

            // if we had a carry forwards of AI bots that had been on the top score so far and that have moved up 1 point due to a correct answer then deal with them
            if ($carry){
                $scorefrequencies[] = $carry;
            }
            # echo "$step: scorefrequencies: " . json_encode($scorefrequencies) . "\n";
        }

        // round down the user's points for the sake of comparison
        $ownpoints = floor($ownpoints);

        // determine how many AI bots have scores of less than user score - 2
        $passedcount = 0;
        for($i = 0; $i < $ownpoints -2 && $i < count($scorefrequencies); ++$i){
            $passedcount += $scorefrequencies[$i];
        }

        // construct the result
        $result=[];
        $result[-3] = $passedcount;
        for($i = -2; $i <= 2; ++$i){
            $idx = $ownpoints + $i;
            $count = isset( $scorefrequencies[$idx] )? $scorefrequencies[$idx]: 0;
            $passedcount += $count;
            $result[$i] = $count;
        }
        $result[3] = $nbcompetitors - $passedcount;
        return $result;
    }
}