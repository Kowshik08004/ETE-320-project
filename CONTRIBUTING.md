# Contributing to RFID Attendance System

First off, thank you for considering contributing to this project! 🎉

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples**
- **Describe the behavior you observed and what you expected**
- **Include screenshots if applicable**
- **Include your environment details** (OS, PHP version, MySQL version)

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- **Use a clear and descriptive title**
- **Provide a detailed description of the suggested enhancement**
- **Explain why this enhancement would be useful**
- **List any similar features in other systems**

### Pull Requests

1. **Fork the repo** and create your branch from `main`
2. **Make your changes**:
   - Follow the existing code style
   - Comment your code where necessary
   - Update documentation if needed
3. **Test your changes** thoroughly
4. **Commit your changes**:
   - Use clear commit messages
   - Reference issues if applicable
5. **Push to your fork** and submit a pull request

## Code Style Guidelines

### PHP
- Use PSR-12 coding standard
- Use meaningful variable and function names
- Comment complex logic
- Avoid deeply nested code

```php
// Good
function getUserAttendance($userId, $courseId) {
    // Fetch attendance records for specific user and course
    $query = "SELECT * FROM attendance WHERE user_id = ? AND course_id = ?";
    // ... implementation
}

// Bad
function get($u, $c) {
    $q = "SELECT * FROM attendance WHERE user_id = ? AND course_id = ?";
    // ... implementation
}
```

### JavaScript
- Use camelCase for variables and functions
- Use const/let instead of var
- Add semicolons
- Use meaningful names

```javascript
// Good
const studentData = fetchStudentInfo(studentId);

// Bad
var d = get(id);
```

### SQL
- Use uppercase for SQL keywords
- Use meaningful table and column names
- Always use prepared statements
- Add indexes for frequently queried columns

### HTML/CSS
- Use semantic HTML5 elements
- Keep CSS modular
- Use consistent indentation (2 or 4 spaces)
- Make interfaces responsive

## Development Setup

1. Install XAMPP/WAMP (Apache, MySQL, PHP)
2. Clone your fork
3. Import the database schema
4. Configure database connection
5. Test locally before pushing

## Testing

Before submitting a pull request:

- [ ] Test all modified functionality
- [ ] Test on different browsers (Chrome, Firefox, Edge)
- [ ] Check for PHP errors/warnings
- [ ] Test database operations
- [ ] Verify no SQL injection vulnerabilities
- [ ] Test with different user roles

## Documentation

- Update README.md if you change functionality
- Comment complex code sections
- Update API documentation if applicable
- Add inline comments for non-obvious code

## Git Commit Messages

- Use present tense ("Add feature" not "Added feature")
- Use imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit first line to 72 characters
- Reference issues and pull requests

Examples:
```
Add attendance export to Excel feature
Fix session timeout issue #123
Update user authentication flow
Refactor database connection handling
```

## Project Structure

When adding new files, follow the existing structure:

```
├── *.php                 # Main application files
├── js/                   # JavaScript files
├── css/                  # Stylesheets
├── icons/                # Image assets
├── RFID/                 # Arduino code
└── docs/                 # Documentation (if added)
```

## Security

- Never commit sensitive data (passwords, API keys)
- Always use prepared statements for SQL queries
- Validate and sanitize all user inputs
- Use password hashing (bcrypt/argon2)
- Implement CSRF protection
- Keep dependencies updated

## Questions?

Feel free to open an issue for questions or clarifications.

Thank you for contributing! 🚀
