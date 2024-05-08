<?php
/* 
 * Authors: Alex Myron, Morghan Jones, Rylee Domonkos
 * Last Edited: 4/28/24
*/

//////////////////////////////////////////////////////////////// Main
// Creates global variables
$originalHeaders;
$data;
$dateInfo;
$matches;
$dateMonth;
$dateYear;

// Runs main functions 
createFolders(); 
extractData(); 
createDate();
$headers = ['P1 First Name',	'P1 Last Name',	'P1 Email',	'P1 Generation',	'P1 Time Zone',	'P1 Time Match Sunday',	'P1 Time Match Monday', 'P1 Time Match Tuesday', 'P1 Time Match Wednesday', 'P1 Time Match Thursday', 'P1 Time Match Friday', 'P1 Time Match Saturday', 'Group',	'P2 First Name', 'P2 Last Name',	'P2 Email',	'OP Generation',	'P2 Time Zone',	'P2 Time Match Sunday',	'P2 Time Match Monday', 'P2 Time Match Tuesday', 'P2 Time Match Wednesday', 'P2 Time Match Thursday', 'P2 Time Match Friday', 'P2 Time Match Saturday',	'Meetup Naming Structure'];

$matches = findBestMatches($data);
outputFiles();
echo("Matches have been made!");

//////////////////////////////////////////////////////////////// Setup and Import

// Generate folders if they don't exist
// No perameters or returns
function createFolders() {
  if (!is_dir("Matches")) {
    mkdir("Matches");
  }
  if (!is_dir("Old CSV")) {
    mkdir("Old CSV");
  }
}

// Gets and stores information from CSV file
// Stores data in global variable $data and $originalHeaders
// No perameters or returns
function extractData() {
  global $headers, $data, $originalHeaders;
  // Open the CSV file
  $file = fopen("SORTME.csv","r");

  if(!$file) {
    $myfile = fopen("Error.txt", "w");
    $txt = "SORTME.CSV is not found. Please place it on desktop and ensure it is named correctly.\n";
    fwrite($myfile, $txt);
    fclose($myfile);
    return false;
  }
  // Creates $originalHeaders and $data
  // Rylee - Does this read in empty as NULL or ""?
  $originalHeaders = fgetcsv($file);
  $data = array();
  while(!feof($file)) {
    array_push($data,fgetcsv($file));
  }
  fclose($file);

  // Sorts data
  sort($data);
}

// Generates the date info for renaming file
// Puts date info in global variable $dateInfo, $dateMonth, and $dateYear
// No perameters or returns
function createDate() {
  global $dateInfo, $dateMonth, $dateYear;
  $date = getdate();
  $dateMonth = $date['mon'];
  $dateYear = $date['year'];

  // Subtract 4 from the current hour
  $hour = $date['hours'] - 4;
  if ($hour < 0) {
    // If the result is negative, adjust it to ensure it stays within the range of 0-23
    $hour += 24;
  }

  $dateInfo = ' ' . $date['mon'] . '-' . $date['mday'] . '-' . $date['year'] . ' ' . $hour . ';' . $date['minutes'] . ';' . $date['seconds'];
}

// Returns the season based on the month of the year it is
// Perameters: $month - int
// Returns: season - string
function getSeason($month) {
  if (($month >= 11 && $month <= 12) || $month == 1) {
    return "w";
  } else if ($month >= 2 && $month <= 4) {
    return "sp";
  } else if ($month >= 5 && $month <= 7) {
    return "sum";
  } else if ($month >= 8 && $month <= 10) {
    return "f";
  }
}

// Uses $match, a single match from $matches, $dateMonth, and $dateYear; to generate meetup link
// Perameters: $match - array, $dateMonth - int, $dateYear - int
// Returns: meetupName - string
function generateMeetupLink($person1, $person2, $month, $year) {
  $season = getSeason($month);
  $name1 = $person1[2] . $person1[3][0];
  $name2 = $person2[2] . $person2[3][0];
  $meetupName = $season . strval($year)[2] . strval($year)[3] . '_Class_' . $name1 . '_' . $name2;
  return strtolower($meetupName);
}

//////////////////////////////////////////////////////////////// Matching Details

function peopleMatch($person1, $person2) {
  ///global $dateMonth, $dateYear;
  if (languageMatch($person1, $person2) && 
      generationsCompatible($person1, $person2) && 
      organizationMatch($person1, $person2)) {
    $time = timeMatch($person1, $person2);
    if ($time)
    { 
     $result = matchFormat($person1, $person2, $time);
     //$result = array_push($result, generateMeetupLink($result, $dateMonth, $dateYear)); 
     return $result;
    }
  }
  return false;
}

// extract the time zone code
function extractTimeCode($timeZoneString) {
    if (preg_match('/\(GMT\s*([+\-]\d{1,2}):\d{2}\)/', $timeZoneString, $num)) {
        return $num[1];
    }
    return false;
}

function timeMatch($person1, $person2) {
  // Initialize an array to store the common time slots for each day (0 is Sunday)
  $commonTimeSlots = [];

  $person2ZoneModifier = extractTimeCode($person1[7]) - extractTimeCode($person2[7]);
  $evenModifier = $person2ZoneModifier;

  // Ensure the result is even
  if ($person2ZoneModifier % 2 !== 0 and $person2ZoneModifier > 0) {
    $evenModifier = $person2ZoneModifier + 1; // If odd and positive, add 1 to make it even
  } 
  else if ($person2ZoneModifier % 2 !== 0 and $person2ZoneModifier < 0) {
    $evenModifier = $person2ZoneModifier - 1; // If odd and negative, add -1 to make it even
  }

  $person1Avail = array_slice($person1, 17);
  $person2Avail = array_slice($person2, 17);

  for ($i = 0; $i <= 6; $i++) {
    $person1Day = array_slice($person1Avail, $i * 3, 3);
    $person2Day = array_slice($person2Avail, $i * 3, 3);

    $person1Times = [];
    $person2Times = [];
    foreach ($person1Day as $timeRange) {
      $times = array_map('intval', explode(',', preg_replace('/[^0-9,]/', '', $timeRange)));
      $person1Times = array_merge($person1Times, $times);
    }
    foreach ($person2Day as $timeRange) {
      $times = array_map('intval', explode(',', preg_replace('/[^0-9, ]/', '', $timeRange)));
      if($person2ZoneModifier!=0){
        for ($j = 0; $j < count($times); $j++) { 
          // add modifier to each element for person 2 to translate into person 1s timezone
          $times[$j] += $evenModifier;
        }
      }
      $person2Times = array_merge($person2Times, $times);
    }

    // Check if there are common time slots for this day
    $countSlots = 0;
    $dayCommonTimeSlots = []; //are you kidding
    foreach ($person1Times as $P1time){
      foreach ($person2Times as $P2time){
        if($P1time == $P2time && !$P1time == 0){
          $dayCommonTimeSlots[$countSlots] = $P1time;
          $countSlots++;
        }
      }
    }
    if (!empty($dayCommonTimeSlots)) {
      $commonTimeSlots[$i] = $dayCommonTimeSlots;
    }
  }

   return getModifiedCommonTimeSlots($commonTimeSlots, $person2ZoneModifier);
}

function getModifiedCommonTimeSlots($commonTimeSlots, $person2ZoneModifier) {
    // Initialize an array to store modified time slots
    $modifiedTimeSlots = [];

    if (count($commonTimeSlots) > 0) {
        // Process days 0 to 6 and populate $modifiedTimeSlots
        for ($i = 0; $i <= 6; $i++) {
            // Ensure $commonTimeSlots[$i] exists and is an array
            if (isset($commonTimeSlots[$i]) && is_array($commonTimeSlots[$i])) {
                $modifiedTimeSlots[$i] = $commonTimeSlots[$i]; // Copy original time slots
                $modifiedTimeSlots[$i + 7] = $commonTimeSlots[$i]; // Duplicate for days 7 to 13
            } else {
                $modifiedTimeSlots[$i] = []; // Initialize empty array if original is missing or not array
                $modifiedTimeSlots[$i + 7] = []; // Initialize empty array for days 7 to 13
            }
        }

        // Apply modifier to time slots for days 7 to 13 if $person2ZoneModifier > 0
        if ($person2ZoneModifier > 0) {
            for ($i = 7; $i <= 13; $i++) {
                if (!empty($modifiedTimeSlots[$i])) {
                    // Apply modifier to each time slot in the array
                    $modifiedTimeSlots[$i] = array_map(function($slot) use ($person2ZoneModifier) {
                        return $slot - $person2ZoneModifier;
                    }, $modifiedTimeSlots[$i]);
                }
            }
        }

        return $modifiedTimeSlots; // Return the modified time slots
    }

    return false; // Return false if $commonTimeSlots is empty
}


// check for common languages, return array of matching languages or false
function languageMatch($person1, $person2){
  $person1Languages = explode(",", $person1[5]);
  $person2Languages = explode(",", $person2[5]);
  $commonLanguages = array_intersect($person1Languages, $person2Languages);
  // Check if there are any common languages
  if (!empty($commonLanguages)) {
    return $commonLanguages;
  }
  return false; 
}

// checks if both people are from the same organization
// Perameters: $person1 - array, $person2 - array
// Returns: boolean
function organizationMatch($person1, $person2){
  if ($person1[16] == $person2[16]) {
    return true;
  }  
  return false;
}

// Assuming that person1 and 2 are arrays of info
// Perameters: $person1 - array, $person2 - array
// Returns: boolean
function generationsCompatible($person1, $person2) {
  // Creates needed variables
  $person1Generation;
  $person2Generation;
  $isGenerationallyCompatable;
  // $neededGenerationalDifference is how many generations apart they must be to be compatible
  $neededGenerationalDifference = 2;

  // Creates list of all generation oldest to newest
  // To create more younger generations add new options to the end of the list how they are displayed in the csv file
  // To create more older generations add new options to the begining of the list how they are displayed in the csv file
  $generations = ['Silent Generation (1925-1945)', 'Baby Boomer (1946-1964)', 'GenX (1965-1980)', 'Millennial (1981-1997)', 'Gen Z or younger (1998 or after)'];

  // Finds the generation of each person
  foreach ($generations as $info) {
    if ($person1[4] == $info) {
      $person1Generation = $info;
    }
    if ($person2[4] == $info) {
      $person2Generation = $info;
    } 
  }

  // Determinds if the generations are compatible or not
  if ($person1Generation == 'GenX (1965-1980)' || $person2Generation =='GenX (1965-1980)') {
    if ($person1[0] == $person2[0]) {
      // If one is GenX and both are students or nonstudents they are not compatable
      $isGenerationallyCompatable = false;
    } else {
      // Checks if the generations are within the needed difference
      $isGenerationallyCompatable = ((int)array_search($person1Generation, $generations) - (int)array_search($person2Generation, $generations) >= $neededGenerationalDifference || (int)array_search($person1Generation, $generations) - (int)array_search($person2Generation, $generations) <= (-1 * $neededGenerationalDifference));
    }
  } else {
    // Checks if the generations are within the needed difference
    $isGenerationallyCompatable = ((int)array_search($person1Generation, $generations) - (int)array_search($person2Generation, $generations) >= $neededGenerationalDifference || (int)array_search($person1Generation, $generations) - (int)array_search($person2Generation, $generations) <= (-1 * $neededGenerationalDifference));
  }

  // Returns if the generations are compatible or not
  return $isGenerationallyCompatable;

}

//////////////////////////////////////////////////////////////// Matching Optimization

/*
  
*/
// Find the largest list of one-to-one matches
function findBestMatches($data) {
    $numPeople = count($data);
    $bestMatches = []; // This is the final array of all the best matches that was found
    $matchedIndices = []; // This is an array that keeps track of which people have already been matched
    $currentMatch = []; // This is the current match that is being evaluated
    for ($i = 0; $i < $numPeople - 1; $i++) {
        if (in_array($i, $matchedIndices)) {
            continue; // Skip if person $i is already matched
        }
        for ($j = $i + 1; $j < $numPeople; $j++) {
          $areMatches = peopleMatch($data[$i], $data[$j]);
          // if i and j are matches and j isn't matched
          if (!in_array($j, $matchedIndices) && $areMatches != false) {
            array_push($currentMatch, $areMatches);  
            // Check if the current match is larger than the current best match
            if (empty($bestMatches) || count($currentMatch) > count($bestMatches)) {
              $bestMatches = $currentMatch;
            } elseif (count($currentMatch) == count($bestMatches)) {
              // If the current match has the same size as the best match, add it to the array
              $bestMatches = $currentMatch;
            }
            // Mark both persons as matched
            array_push($matchedIndices, $i, $j);
            break; // Move to the next person after finding a match for $i
            }
        }
    }
    array_push($bestMatches, '');
    global $originalHeaders;
    array_push($bestMatches, $originalHeaders);
    for ($i = 0; $i < $numPeople; $i++){
      if (!in_array($i, $matchedIndices)) {
        array_push($bestMatches, $data[$i]);
      }
    }
    return $bestMatches;
}

//////////////////////////////////////////////////////////////// Other

function formatMilitaryTime($times) {
    // Array to store the output
    $output = [];

    // Loop through each time in the input array
    foreach ($times as $time) {
        // Determine if the time is in the AM or PM period
        $startPeriod = ($time % 24) < 12 ? 'am' : 'pm';

        // Calculate the start hour (12-hour format)
        $startHour = ($time % 24) < 12 ? ($time % 12 === 0 ? 12 : $time % 12) : (($time - 12) % 12 === 0 ? 12 : ($time - 12) % 12);

        // Calculate the end hour (12-hour format)
        $endHour = (($time + 2) % 24) < 12 ? (($time + 2) % 12 === 0 ? 12 : ($time + 2) % 12) : ((($time + 2) - 12) % 12 === 0 ? 12 : (($time + 2) - 12) % 12);

        // Determine if the end time is in the AM or PM period
        $endPeriod = (($time + 2) % 24) < 12 ? 'am' : 'pm';

        // Format the output string (e.g., "6am-8am")
        $formattedTime = $startHour . $startPeriod . '-' . $endHour . $endPeriod;

        // Add the formatted time to the output array
        $output[] = $formattedTime;
    }

    // Return the array of formatted times
    return $output;
}

function matchFormat($person1, $person2, $time){
  /* 
  Desired output: [Rylee,Domonkos,domonkrg@miamioh.edu,Gen Z,EST,"10am-12pm,2pm-4pm",Miami University,Carrie,Powell,sagedom@yahoo.com,Gen X,MST,"8am-10am,12pm-2pm", S24_Class_RyleeD_CarrieP]
  1 user_email, 2 first_name, 3 last_name, 4 user_generation, 7 avid_user_timezone, 16 what_group 
  */
  //Get timematching in correct format for
  global $dateMonth, $dateYear;
  return
    [$person1[2], // person1's first name
    $person1[3], // person1's last name
    $person1[1], // email
    $person1[4], // generation
    $person1[7], // itmezone
    implode(", ", formatMilitaryTime($time[0])), // Common time slots for Sunday
    implode(", ", formatMilitaryTime($time[1])), // Common time slots for Monday
    implode(", ", formatMilitaryTime($time[2])), // Common time slots for Tuesday
    implode(", ", formatMilitaryTime($time[3])), // Common time slots for Wednesday
    implode(", ", formatMilitaryTime($time[4])), // Common time slots for Thursday
    implode(", ", formatMilitaryTime($time[5])), // Common time slots for Friday
    implode(", ", formatMilitaryTime($time[6])), // Common time slots for Saturday
    $person1[16], // group
    $person2[2], // person2's first name
    $person2[3], // person2's last name
    $person2[1], // email
    $person2[4], // generation 
    $person2[7], // timezone
    implode(", ", formatMilitaryTime($time[7])), // Common time slots for Sunday
    implode(", ", formatMilitaryTime($time[8])), // Common time slots for Monday
    implode(", ", formatMilitaryTime($time[9])), // Common time slots for Tuesday
    implode(", ", formatMilitaryTime($time[10])), // Common time slots for Wednesday
    implode(", ", formatMilitaryTime($time[11])), // Common time slots for Thursday
    implode(", ", formatMilitaryTime($time[12])), // Common time slots for Friday
    implode(", ", formatMilitaryTime($time[13])), // Common time slots for Saturday
    generateMeetupLink($person1, $person2, $dateMonth, $dateYear) // meetup link
     ];
}

//////////////////////////////////////////////////////////////// CSV Exporting

// Moves old CSV files and creates output file
// Uses global variables $headers, $data, $dateInfo, and $matches
// No parameters or returns
function outputFiles() {
  global $headers, $data, $dateInfo, $matches;
  $newLine = false;
  
  // Move SORTME
  rename('SORTME.csv', 'Old CSV/SORTME' . $dateInfo . '.csv');

  // Writes Headers
  $fh = fopen('Matches/Matches' . $dateInfo . '.csv', "w");
  fputcsv($fh, $headers);

  // Writes all matches to the output CSV file
  foreach ($matches as $match) {
    fputcsv($fh, is_array($match) ? $match : []);
    //fputcsv($fh, array_slice($match, 0, 14));
  }
  fclose($fh);
}

?>