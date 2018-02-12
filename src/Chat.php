<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $applicants;
    protected $businesses;

    function __construct(){
        $this->clients = new \SplObjectStorage;
        $this->applicants = [];
        $this->businesses = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New Connection!({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $userInfo = json_decode($msg);
        $user = [];
        $user['userInfo'] = $userInfo;
        $user['from'] = $from;
        if($userInfo->type === 1){
            $toSendApplicant = [];
            $toSendToBusiness = [];
            echo "Applicant";
            array_push($this->applicants, $user);
            foreach($this->businesses as $business){
                $jobsToSend = [];
                foreach($business['userInfo']->requirements as $job){
                    $score = 0;
                    $matchingSkills = [];
                    foreach($job->jobRequirements as $jr){
                        foreach($userInfo->requirements as $skill){
                            if($skill->skill === $jr->requirement && $skill->years_exp >= $jr->years_exp){
                                $score++;
                                array_push($matchingSkills, [
                                    'jSkill' => $jr->requirement,
                                    'aSkill' => $skill->skill,
                                    'jYears' => $jr->years_exp,
                                    'aYears' => $skill->years_exp
                                ]);
                            }
                        }
                    }
                    if($score > 1){
                        $job->matchingSkills = $matchingSkills;
                        array_push($jobsToSend, $job);
                    }
                }
                array_push($toSendApplicant, [
                    'business' => $business['userInfo'],
                    'jobs' => $jobsToSend
                ]);
                $business['from']->send(json_encode([
                    'message' => 'additionalMatchesBusiness',
                    'user' => $userInfo,
                    'jobMatch' => $jobsToSend
                ]));
            }
            $from->send(json_encode([
                'message' => 'newMatchesApplicant',
                'matchesCount' => count($toSendApplicant),
                'matches' => $toSendApplicant
            ]));
        }

        if($userInfo->type === 2){
            echo "Business";
            array_push($this->businesses, $user);
            $toSendBusiness = [];
            foreach($this->applicants as $applicant){
                $jobMatches = [];
                foreach($userInfo->requirements as $job){
                    $score = 0;
                    $matchingSkills = [];
                    foreach($applicant['userInfo']->requirements as $skill){
                        foreach($job->jobRequirements as $jr){
                            if($skill->skill === $jr->requirement && $skill->years_exp >= $jr->years_exp){
                                $score++;
                                array_push($matchingSkills, [
                                    'jSkill' => $jr->requirement,
                                    'aSkill' => $skill->skill,
                                    'jYears' => $jr->years_exp,
                                    'aYears' => $skill->years_exp
                                ]);
                            }
                        }
                    }
                    $job->matchingSkills = $matchingSkills;
                    if($score > 1){
                        array_push($jobMatches, $job);
                    }
                }
                array_push($toSendBusiness, [
                    'applicant' => $applicant,
                    'jobMatch' => $jobMatches
                ]);
                $applicant['from']->send(json_encode([
                    'message' => 'additionalMatchesApplicant',
                    'jobMatches' => $jobMatches,
                    'business' => $user
                ]));
            }
            $from->send(json_encode([
                'message' => 'newMatchesBusiness',
                'matches' => $toSendBusiness,
                ]));
        }
    }

    public function onClose(ConnectionInterface $conn) {

        $this->clients->detach($conn);
        foreach ($this->applicants as $inda => $a){
            if($a['from'] === $conn){
                unset($this->applicants[$inda]);
                echo "Successfully unset applicants index {" . $inda . "} \n";       
                echo "\n";       
            }
        }
    
        foreach ($this->businesses as $indb => $b){
            if($b['from'] === $conn){
                unset($this->businesses[$indb]);
                echo count($this->businesses);
                echo "Successfully unset businesses index {" . $indb . "} \n";
                echo "\n";
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";        
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}