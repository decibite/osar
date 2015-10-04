<?php

/*
                This project is started to provide a time and day of week based auto-responder to e-mails.

                This script should be run as a cronjob at a regular interval, and it will be configured with a date and time to respond with a given message.

                I think I will hard code the values for now, but ideally this will become, or be linked to, a web interface for easy modifications.

*/

/*
        This is a alpha, so lets do it in one clean file.
        Functions at the top, running code/logic at the bottom.
*/

//Functions

function getMessages($mailboxPath, $username, $password) {

        $imap = imap_open($mailboxPath, $username, $password);

        $numMessages = imap_num_msg($imap);

        for ($i = $numMessages; $i > ($numMessages - 20); $i--) {
                $header = imap_header($imap, $i);

                $fromInfo = $header->from[0];
                $replyInfo = $header->reply_to[0];

                $details = array(
                        "fromAddr" => (isset($fromInfo->mailbox) && isset($fromInfo->host))
                            ? $fromInfo->mailbox . "@" . $fromInfo->host : "",
                        "fromName" => (isset($fromInfo->personal))
                            ? $fromInfo->personal : "",
                        "replyAddr" => (isset($replyInfo->mailbox) && isset($replyInfo->host))
                            ? $replyInfo->mailbox . "@" . $replyInfo->host : "",
                        "replyName" => (isset($replyTo->personal))
                            ? $replyto->personal : "",
                        "subject" => (isset($header->subject))
                            ? $header->subject : "",
                        "udate" => (isset($header->udate))
                            ? $header->udate : "",
                    );

                $uid = imap_uid($imap, $i);

                $messages[$i]['from'] = $details["fromAddr"];
                $messages[$i]['uid'] = $uid;
                $messages[$i]['subject'] = $header->subject;
				$messages[$i]['udate'] = $header->udate;

  /*echo "<ul>";
    echo "<li><strong>From:</strong>" . $details["fromName"];
    echo " " . $details["fromAddr"] . "</li>";
    echo "<li><strong>Subject:</strong> " . $details["subject"] . "</li>";
    echo '<li><a href="mail.php?folder=' . $folder . '&uid=' . $uid . '&func=read">Read</a>';
    echo " | ";
    echo '<a href="mail.php?folder=' . $folder . '&uid=' . $uid . '&func=delete">Delete</a></li>';
    echo "</ul>";*/

        }

        return $messages;
}

function isResponderActive($OutOfOffice) {
	if ($OutOfOffice[date('N')] != 'off') {
		if ($OutOfOffice[date('N')] != 'all') {
			$exp = explode('-', $OutOfOffice[date('N')]);
			$exp2 = explode(':', $exp[0]);
			
			if (date('H') >= $exp2[0] && date('i') >= $exp2[1]) {
				if ($exp[1] == '*') {
					$isOn = 'yes';
				} else {
					$exp3 = explode(':', $exp[1]);
					$endHour = $exp3[0];
					$endMin = $exp3[1];
					
					if (date('H') <= $endHour && date('i') <= $endMin) {
						$isOn = 'yes';
					} else {
						$isOn = 'no';
					}
				}
			} else {
				$isOn = 'no';
			}
		} else {
			$isOn = 'yes';
		}
	} else {
		$isOn = 'no';
	}
	return $isOn;
}

function sendReply($uid, $subject, $udate, $OutOfOffice) {
	//$fromAddress = '';
	//Search file for our uid.
	$replied = 0;
	$handle = @fopen("replyList.php", "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			if ($buffer == $uid) {
				$replied = 1;
			}
		}
		fclose($handle);
	}
	
	//Add check to see if we have replied to a message with this subject?
	$handle = @fopen("subjectList.php", "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			if ($buffer == $subject) {
				$replied = 2;
			}
		}
		fclose($handle);
	}
	
	//Add check to see if we have already replied to this address?
	/*
	$handle = @fopen("raddressList.php", "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			if ($buffer == $fromAddress) {
				$replied = 3;
			}
		}
		fclose($handle);
	}*/
	
	//Add check to see if it is REALLY important?
	/*
	$handle = @fopen("iaddressList.php", "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			if ($buffer == $fromAddress) {
				$replied = 9;
			}
		}
		fclose($handle);
	}*/
	
	//Check to make sure message udate is within the OOO range
        if ($OutOfOffice[date('N')] != 'all') {
                $explode = explode("-", $OutOfOffice[date('N')]);
                if ($explode[0] == '*') {
                        $OOOStartTimestamp = strtotime('12pm');
                } else {
                        $OOOStartTimestamp = strtotime($explode[0]);
                }
                if ($explode[1] == '*') {
                        $OOOEndTimestamp = strtotime('12pm tomorrow');
                } else {
                        $OOOEndTimestamp = strtotime($explode[1]);
                }
        } else {
                $OOOStartTimestamp = strtotime('12pm');
                $OOOEndTimestamp = strtotime('12pm tomorrow');
        }
        //If we have not replied and the message was sent within the OOO range
        if ($replied == 0 && $udate >= $OOOStartTimestamp && $udate <= $OOOEndTimestamp) {
                $sendReply = 'yes';
        } else {
        //If we have already replied to the message, or if the udate is out of the OOO range
                $sendReply = 'no';
        }

        return $sendReply;
	
}

//Variables/Constants
$mailboxPath = '{mail.negative0.net:993/imap/ssl}INBOX';
$username = 'bleavitt@decibite.com';
$password = '';

//Date information
$OutOfOffice[1] = 'off';
$OutOfOffice[2] = 'off';
$OutOfOffice[3] = 'off';
$OutOfOffice[4] = 'off';
$OutOfOffice[5] = '17:00-*';
$OutOfOffice[6] = 'all';
$OutOfOffice[7] = 'all';

//Reply Message Information
$from = '';
$subject = '';
$message = '';
//When Run

//Check current date/time and see how it compares to out of office date/time.
$isAlive = isResponderActive($OutOfOffice);

if ($isAlive == 'yes') {
	//If we find an active responder get the last 20 messages from the Inbox
	$x = getMessages($mailboxPath, $username, $password);

	//Go through returned list and check to see if we have alread replied.
	foreach($x as $y) {
		$sendReply = sendReply($y['uid'], $y['subject'], $y['udate'], $OutOfOffice);
		if ($sendReply == 'yes') {
			//Send E-Mail and record that message has been something
			$headers = "From: ".$from;
			mail($y['from'],$subject,$message, $headers);
			
			//Add UID to file
			$file = 'replyList.php';
			// Open the file to get existing content
			$current = file_get_contents($file);
			// Append a new person to the file
			$current .= $y['uid']."\n";
			// Write the contents back to the file
			file_put_contents($file, $current);
			
			//Add Subject to file
			$file = 'subjectList.php';
			// Open the file to get existing content
			$current = file_get_contents($file);
			// Append a new person to the file
			$current .= $y['subject']."\n";
			// Write the contents back to the file
			file_put_contents($file, $current);
		}
	}
}
