# zipcode_database
Zipcode Database Service

##Installation
1. Create MySQL database and create the "zipData" table.
2. Initialize the "zipData" table using the "zipData.sql" dump.
3. Initailze the "config.php" file the "example_configuration.php" file.<br>
`$ cp example_config.php config.php`
4. Fill-in the missing data in the "config.php" file.

###Note
The Zipcode Database Service requires a [USPS API](https://www.usps.com/business/web-tools-apis/welcome.htm) and [ZipCodeAPI](https://www.zipcodeapi.com/) key.

###File Structure
```
.
├── classes/
|  └── zipCodeUtilities.php
├── DAO/
|	└── mysqlDAO.php
├── config.php
├── example.php
├── example_config.php
├── README.md
├── zipData.sql
```
