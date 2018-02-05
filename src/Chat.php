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
            echo "Applicant";
            array_push($this->applicants, $user);
            foreach($this->businesses as $business){
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
                        array_push($toSendApplicant, [
                            'matchingSkills' => $matchingSkills,
                            'business' => $business,
                            'job' => $job
                        ]);
                    }
                }
            }
            $from->send(json_encode([
                'message' => 'newMatches',
                'matchesCount' => count($toSendApplicant),
                'matches' => $toSendApplicant
            ]));
        }

        if($userInfo->type === 2){
            echo "Business";
            array_push($this->businesses, $user);
            foreach($this->applicants as $applicant){
                $applicant['from']->send(json_encode($userInfo));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        foreach ($this->applicants as $inda => $a){
            if($a['from'] === $conn){
                unset($this->applicants[$inda]);
                echo "Successfully unset $applicants index {" . $inda . "} \n";       
                echo "\n";       
            }
        }
    
        foreach ($this->businesses as $indb => $b){
            if($b['from'] === $conn){
                unset($this->businesses[$indb]);
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