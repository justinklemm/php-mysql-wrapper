PHP MySQL Wrapper
=================

This is a very old PHP class that I created circa 2006 to act as a wrapper for PHP's MySQL functions. It has a number of nice functions that accept parameters via arrays and build queries with them. It's used statically, not actually instantiated. For instance:

    // Connect to database
    DB::connect('localhost', 'db', 'root', 'pass');

    // Get an array of all employees with the first name 'fred' in descending order by their start date
    $employees = DB::selectArray('employees', '*', array('first_name' => 'fred'), 'date_start', true);
    
    // Disconnect from database
    DB::disconnect();

This makes thw wrapper fast and easily accessibile in functions, but also quite limited. For instance, it's not meant for situations in which you will need to connect to multiple databases concurrently.

This class uses PHP's (very dated) `mysql_*` functions which have been depreciated as of PHP 5.5. My intent is to update this classes to one of PHP's newer MySQL libraries.

There are certainly more robust database solutions out there, but for simple projects, this class may prove useful.
