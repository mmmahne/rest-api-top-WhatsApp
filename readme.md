# WhatsApp OTP API Documentation

This API provides OTP (One-Time Password) functionality via WhatsApp using the Fonnte service.

## API Endpoints

Base URL: `your-domain/api/otp.php`

### 1. Send OTP
Send an OTP code to a phone number via WhatsApp.

**Endpoint:** `?action=send`  
**Method:** POST  
**Request Body:**

json
{
"phone_number": "your_phone_number"
}

json
{
"status": "success",
"message": "OTP sent successfully"
}


### 2. Validate OTP
Validate the OTP code received by the user.

**Endpoint:** `?action=validate`  
**Method:** POST  
**Request Body:**

son
{
"phone_number": "your_phone_number",
"otp": "123456"
}

son
{
"status": "success",
"message": "OTP verified successfully"
}


## Important Notes

1. The OTP code expires after 5 minutes
2. There is a 1-minute cooldown between OTP requests for the same number
3. The OTP is a 6-digit number
4. Make sure to replace the Fonnte token in the code with your own token

## Error Messages

- "Please wait 1 minute before requesting another OTP"
- "Phone number is required"
- "Phone number and OTP are required"
- "OTP has expired"
- "Invalid OTP"
- "Invalid action"
- "Invalid request method"

## Setup Requirements

1. PHP server with PDO MySQL support
2. MySQL database with an `otp` table
3. Fonnte API token
4. Proper database configuration in `config/database.php`

## Database Configuration

Update the database configuration in `config/database.php`: