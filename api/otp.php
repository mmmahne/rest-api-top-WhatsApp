<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

class OTPHandler {
    private $conn;
    private $table_name = "otp";
    private $fonnte_token = "use your fonnte token here"; // Updated with your token

    public function __construct($db) {
        $this->conn = $db;
    }

    public function sendOTP($phone_number) {
        try {
            // Check if there was a recent OTP request
            $query = "SELECT waktu FROM " . $this->table_name . " 
                     WHERE nomor = :nomor ORDER BY waktu DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":nomor", $phone_number);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if(time() - $row['waktu'] < 60) { // 1 minute cooldown
                    return ["status" => "error", "message" => "Please wait 1 minute before requesting another OTP"];
                }
            }
            
            // Delete existing OTP for this number
            $query = "DELETE FROM " . $this->table_name . " WHERE nomor = :nomor";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":nomor", $phone_number);
            $stmt->execute();

            // Generate new 6-digit OTP
            $otp = rand(100000, 999999);
            $current_time = time();

            // Insert new OTP
            $query = "INSERT INTO " . $this->table_name . " (nomor, otp, waktu) VALUES (:nomor, :otp, :waktu)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(":nomor", $phone_number);
            $stmt->bindParam(":otp", $otp);
            $stmt->bindParam(":waktu", $current_time);

            if($stmt->execute()) {
                // Send OTP via Fonnte with new message template
                $curl = curl_init();
                $message = "Welcome to Eden Lounge here is your OTP\n\n{$otp}\n\nDo not share this with anyone";
                
                $data = [
                    'target' => $phone_number,
                    'message' => $message,
                    'countryCode'=>'0'
                ];

                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    "Authorization: " . $this->fonnte_token
                ]);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($curl, CURLOPT_URL, "https://api.fonnte.com/send");
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                
                $result = curl_exec($curl);
                curl_close($curl);

                return ["status" => "success", "message" => "OTP sent successfully"];
            }
            
            return ["status" => "error", "message" => "Failed to send OTP"];
        } catch(Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function validateOTP($phone_number, $otp) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE nomor = :nomor AND otp = :otp";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":nomor", $phone_number);
            $stmt->bindParam(":otp", $otp);
            $stmt->execute();

            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if(time() - $row['waktu'] <= 300) { // 5 minutes expiry
                    return ["status" => "success", "message" => "OTP verified successfully"];
                } else {
                    return ["status" => "error", "message" => "OTP has expired"];
                }
            }
            
            return ["status" => "error", "message" => "Invalid OTP"];
        } catch(Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}

// Handle API requests
$database = new Database();
$db = $database->getConnection();
$handler = new OTPHandler($db);

$data = json_decode(file_get_contents("php://input"));

if($_SERVER["REQUEST_METHOD"] === "POST") {
    if(isset($_GET["action"])) {
        switch($_GET["action"]) {
            case "send":
                if(isset($data->phone_number)) {
                    $result = $handler->sendOTP($data->phone_number);
                    echo json_encode($result);
                } else {
                    echo json_encode(["status" => "error", "message" => "Phone number is required"]);
                }
                break;
                
            case "validate":
                if(isset($data->phone_number) && isset($data->otp)) {
                    $result = $handler->validateOTP($data->phone_number, $data->otp);
                    echo json_encode($result);
                } else {
                    echo json_encode(["status" => "error", "message" => "Phone number and OTP are required"]);
                }
                break;
                
            default:
                echo json_encode(["status" => "error", "message" => "Invalid action"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Action is required"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?> 