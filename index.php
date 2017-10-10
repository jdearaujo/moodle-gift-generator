<?php

define("ANSWER_MARK", "*");
define("MAX_LENGTH", 50);
define("ALLOWED_TAGS", "<strong><b><i><em><u>");

$filename = "questions.gift.txt";
$source = "";
$errors = [];

function parseMultipleChoice($lines)
{
    $question = [];
    $options = [];
    foreach ($lines as $j => $line) {
        if ($j == 0) {
            $str = getQuestionOrOptionValue($line);
            $question["name"] = convertToSafeName($str);
            $question["question"] = convertToSafeHTML($str);
            $question["type"] = "multiple-choice";
        } else {
            if (isOption($line)) {
                $options[$j-1]["text"] = convertToSafeHTML(getQuestionOrOptionValue($line));
                $options[$j-1]["answer"] = isAnswer($line);
            } else {
                // handle multiple line question or option
                if (empty($options)) {
                    $question["question"] .= getQuestionOrOptionValue($line);
                } else {
                    $options[$j-2] = (string)$options[$j-2].getQuestionOrOptionValue($line);
                }
            }
        }
    }
    $question["options"] = $options;
    return $question;
}

function parseMatching($lines)
{
    $question = [];
    $subquestions = [];
    foreach ($lines as $j => $line) {
        if ($j == 0) {
            $str = getQuestionOrOptionValue($line);
            $question["name"] = convertToSafeName($str);
            $question["question"] = convertToSafeHTML($str);
            $question["type"] = "matching";
        } else {
            $tmp = explode(ANSWER_MARK, $line);
            $subquestions[$j-1]["text"] = convertToSafeHTML(getQuestionOrOptionValue($tmp[0]));
            $subquestions[$j-1]["answer"] = $tmp[1];
        }
    }
    $question["subquestions"] = $subquestions;
    return $question;
}

function parseTFChoice($lines, $tf)
{
    $question = [];
    $subquestions = [];

            $question["name"] = convertToSafeName($lines[1]);
            $question["question"] = convertToSafeHTML($lines[2]);
            $question["type"] = $tf;

            $subquestions[1]["text"] = $tf;
            $subquestions[2]["answer"] = $lines[1];

    $question["subquestions"] = $subquestions;
    return $question;
}

function isOption($str)
{
    $firstDot = strpos($str, ".");
    if ($firstDot !== false) {
        return !is_numeric(substr($str, 0, $firstDot));
    }
    return false;
}

function isAnswer($str)
{
    return $str[0] == ANSWER_MARK;
}

function isOptionAnswer($str)
{
    return isOption($str) && isAnswer($str);
}

function getQuestionOrOptionValue($str)
{
    $firstDot = strpos($str, ".");
    if ($firstDot !== false) {
        $value = trim(substr($str, $firstDot+1));
        // remove colon from question
        if (is_numeric(substr($str, 0, $firstDot))) {
            $value = str_replace(":", "", $value);
        }
        return $value;
    }
    return trim(str_replace(":", "", $str));
}

function validateQuestionMultipleChoice($question)
{
    $errors = [];
    $question_text = trim($question["question"]);
    if (empty($question_text)) {
        array_push($errors, "Empty question");
    }
    if (count($question["options"]) <= 0) {
        array_push($errors, "No options provided");
    } else {
        $answered = false;
        foreach ($question["options"] as $index => $option) {
            $option_text = trim($option["text"]);
            if (empty($option_text)) {
                array_push($errors, ($index + 1)." : empty option");
            }
            if ($option["answer"] == true) {
                $answered = true;
            }
        }
        if ($answered == false) {
            array_push($errors, "No answer given");
        }
    }
    return $errors;
}

function validateQuestionMatching($question)
{
    $errors = [];
    $question_text = trim($question["question"]);
    if (empty($question_text)) {
        array_push($errors, "Empty question");
    }
    if (count($question["subquestions"]) <= 0) {
        array_push($errors, "No subquestions provided");
    } else {
        foreach ($question["subquestions"] as $index => $option) {
            $option_text = trim($option["text"]);
            $option_answer = trim($option["answer"]);
            if (empty($option_text)) {
                array_push($errors, ($index + 1)." : empty subquestion text");
            }
            if (empty($option_answer)) {
                array_push($errors, ($index + 1)." : empty subquestion answer");
            }
        }
    }
    return $errors;
}

function validateQuestionTF($question, $type)
{
    $errors = [];
    $question_text = trim($question["question"]);
    if (empty($question_text)) {
        array_push($errors, "Empty question");
    }
    if (count($question["subquestions"]) <= 0) {
        array_push($errors, "No details provided");
    } else {
        foreach ($question["subquestions"] as $index => $option) {
            $option_text = trim($option["text"]);
            $option_answer = trim($option["answer"]);
            if (empty($option_text)) {
                array_push($errors, ($index + 1)." : empty subquestion text");
            }
            if (empty($option_answer)) {
                array_push($errors, ($index + 1)." : empty subquestion answer");
            }
        }
    }
    return $errors;
}

function validateQuestions($questions)
{
    $errors = [];
    foreach ($questions as $index => $question) {
        if ($question["type"] == "multiple-choice") {
            $errors_temp = validateQuestionMultipleChoice($question);
        } else if ($question["type"] == "matching") {
            $errors_temp = validateQuestionMatching($question);
        } /*else if ($question["type"] == "truey") {
            $errors_temp = validateQuestionTF($question, "truey");
        } else if ($question["type"] == "falsey") {
            $errors_temp = validateQuestionTF($question, "falsey");
        }*/
        if (count($errors_temp) > 0) {
            $errors[$index] = $errors_temp;
        }
    }
    return $errors;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $filename = !empty($_POST["filename"]) ? trim($_POST["filename"]) : $filename;
    $source = $_POST["source"];
    // remove whitespaces from empty lines
    $source = preg_replace("/\n[ \t]+/m", "\n", $source);
    $questionsRaw = explode("\n\r\n", trim($source));
    // var_dump($questionsRaw);

    $questions = [];
    foreach ($questionsRaw as $i => $q) {
        if (!empty($q)) {
            $lines = explode("\n", trim($q));
            if (strpos(strtolower($lines[0]), "matching") === 0) {
                $questions[$i] = parseMatching($lines);
            } elseif (strpos(strtolower($lines[0]), "multiple") === 0) {
                $questions[$i] = parseMultipleChoice($lines);
            } elseif (strpos(strtolower($lines[0]), 'true') === 0) {
                $questions[$i] = parseTFChoice($lines,"true");
            } elseif (strpos(strtolower($lines[0]), 'false') === 0) {
                $questions[$i] = parseTFChoice($lines, "false");
            } else {
                //do nothing
            }
        }
    }

    $errors = validateQuestions($questions);
    //var_dump($questions);
    /*echo "<pre>";
    print_r($lines);
    echo "</pre>";
    exit;*/

    if (count($errors) == 0) {
        renderGift($questions, $filename);
    }
}

function convertToSafeName($str)
{
    if (!empty($str)) {
        $len = strlen($str) < MAX_LENGTH ? MAX_LENGTH : strlen($str);
        $str = trim(substr(strip_tags($str), 0, $len));
    }
    return $str;
}

function convertToSafeHTML($str)
{
    if (!empty($str)) {
        $str = strip_tags($str, ALLOWED_TAGS);
    }
    return $str;
}


function renderGift($questions, $filename)
{

    header('Content-type: text/plain');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    foreach ($questions as $q) {
        echo "::".$q["name"]."::";
        echo "[html]<p>".$q["question"];

        if ($q["type"] == "multiple-choice") {
            echo "<br></p>{\n";
            foreach ($q["options"] as $o) {
                echo "\t";
                echo ($o["answer"]) ? "=" : "~";
                echo "<p>".$o["text"]."</p>\n";
            }
        } elseif ($q["type"] == "matching") {
            echo "<br></p>{\n";
            foreach ($q["subquestions"] as $o) {
                echo "\t=<p>".$o["text"]."<br></p> -> ".$o["answer"]."\n";
            }
        } elseif ($q["type"] === "true") {
            echo "{TRUE";
        } elseif ($q["type"] === "false") {
            echo "{FALSE";
        }
        echo "}\n\n";
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || count($errors) > 0) {
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link rel="shortcut icon" href="favicon.ico" />
    <title>Moodle GIFT Generator</title>
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.1.8/semantic.min.css">
    <style type="text/css">

    h1.ui.center.header {
        margin-top: 2em;
        margin-bottom: 1em;
    }

    textarea#source {
        width:100%;
        height: 90%;
        max-height: 90%;
    }

    .ui.container.form, .form form, form .source {
        height: 100%;
    }

    .ui.container.form {
        margin-top: 1em;
    }

    .ui.message {
        margin-bottom: 0.5em !important;
    }

    .message .header {
        margin-bottom: 1em;
    }

    </style>
</head>
<body>
    <h1 class="ui center aligned header">Moodle GIFT Generator</h1>

    <div class="ui container">
        <div class="ui icon message">
          <i class="github alternate icon"></i>
          <div class="content">
            <div class="header">
              <p>Need more help, information or even source code?</p>
            </div>
            <p>Get them on <a href="https://github.com/yohanesgultom/moodle-gift-generator">https://github.com/yohanesgultom/moodle-gift-generator</a></p>
          </div>
        </div>
    </div>

    <?php if (count($errors) > 0) { ?>
    <div class="ui container">
        <div class="ui warning message">
            <i class="close icon"></i>
            <div class="header">
                Error(s) found!
            </div>
            <?php
            foreach ($errors as $index => $error_list) {
                echo "Question ".($index + 1)."<br>";
                echo "<ul>";
                foreach ($error_list as $error) {
                    echo "<li>".$error."</li>";
                }
                echo "</ul>";
            }
            ?>
        </div>
    </div>
    <?php } ?>

    <div class="ui container form">

        <form id="main-form" action="index.php" method="POST">
            <div class="two fields">
            <div class="field">
                <input type="text" name="filename" placeholder="File name" value="<?php echo $filename ?>" required>
            </div>
            <div class="field">
                <button class="blue ui button" type="submit"><i class="icon download"></i> Generate GIFT</button>
            </div>
        </div>

        <div class="ui accordion">
          <div class="title">
            <i class="dropdown icon"></i>
            Need some help?
          </div>
          <div class="content">
            <p class="transition hidden">
                Multiple choice button<br>
                Matching example button<br>
                True/False buttons<br>
                                                
            </p>
            <br>
          </div>
        </div>

        <div>
            <a href="" class="green ui button" id="add_multiple_choice"><i class="icon add"></i> Add multiple choice</a>
            <a href="" class="green ui button" id="add_matching_example"><i class="icon add"></i> Add matching example</a>

            <div class="ui buttons">
              <a href="" class="green ui button" id="add_true_choice"><i class="icon add"></i> Add True</a>
              <div class="or"></div>
              <a href="" class="red ui button" id="add_false_choice"><i class="icon add"></i> Add False</a>
            </div>
        </div>    



            <div class="field source">
                <textarea id="source" name="source" placeholder="Put your question here" required><?php echo $source; ?></textarea>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-1.12.3.min.js" integrity="sha256-aaODHAgvwQW1bFOGXMeX+pC4PZIPsvn2h1sArYOhgXQ=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.1.8/semantic.min.js"></script>
    <script>
        $('#main-form').submit(function() {
            if (!$('input[name=filename]', this).val()) {
                alert('Please provide filename');
                return false;
            } else if (!$('textarea[name=source]', this).val()) {
                alert('Please provide source');
                return false;
            }
            return true;
        });

        $('.ui.accordion')
          .accordion()
        ;

        var currentVal = $("textarea[name=source]").val();     
        $("#add_multiple_choice").on("click", function() {
            event.preventDefault();
            //append multiple choice sample text to textarea
            $("textarea[name=source]").append("Multiple\n1.Who is Indonesia's 1st president? \n*a.Ir. Sukarno\nb.Moh. Hatta \nc.Sukarno Hatta \nd.Suharto \n\n");
        });

        $("#add_matching_example").on("click", function() {
            event.preventDefault();
            //append matching example to textarea
            $("textarea[name=source]").append("Matching.Match each definition about space below \n1.Saturn’s largest moon * Mercury \n2.The 2nd biggest planet in our solar system * Saturn \n3.The hottest planet in our solar system * Venus \n4.Planet famous for its big red spot on it * Jupiter \n5.Planet known as the red planet * Mars \n6.Saturn’s largest moon * Titan\n\n");
        });

        $("#add_true_choice").on("click", function() {
            event.preventDefault();
            //append true choice
            $("textarea[name=source]").append("True\nTrueStatement about Grant\nGrant was buried in a tomb in New York City. \n\n");
        });

        $("#add_false_choice").on("click", function() {
            event.preventDefault();
            //append false choice
            $("textarea[name=source]").append("False\nFalseStatement about the sun\nThe sun rises in the west. \n\n");
        });

    </script>

</body>
</html>

<?php } ?>
