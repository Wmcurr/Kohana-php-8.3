<?php

/**
 * ORM Auth driver.
 *
 * This class handles user authentication and session management
 * using Kohana's ORM (Object Relational Mapping).
 *
 * @package    Kohana/Auth
 */
class Kohana_Auth_ORM extends Auth
{
    /**
     * Checks if a session is active and verifies the user's role (if provided).
     *
     * @param   mixed $role Role name as a string, ORM object, or an array of role names
     * @return  bool Whether the user is logged in and has the required role
     */
    public function logged_in(mixed $role = null): bool
    {
        $user = $this->get_user();

        // If no user is found in the session, return false
        if (!$user) {
            return false;
        }

        // Check if the user object is loaded
        if ($user instanceof Model_User && $user->loaded()) {
            // If no specific role is required, return true
            if (!$role) {
                return true;
            }

            // Fetch roles based on the input type (array, object, or string)
            $roles = is_array($role)
                ? ORM::factory('Role')
                    ->where('name', 'IN', $role)
                    ->find_all()
                    ->as_array(null, 'id')
                : (is_object($role) ? $role : ORM::factory('Role', ['name' => $role]));

            // If roles are invalid or do not match, return false
            if (!$roles || (is_array($role) && count($roles) !== count($role))) {
                return false;
            }

            // Check if the user has the required roles
            return $user->has('roles', $roles);
        }

        return false;
    }

    /**
     * Logs a user in by verifying their credentials.
     *
     * @param   string|Model_User $user Username as a string or a user ORM object
     * @param   string $password Password entered by the user
     * @param   bool $remember Whether to enable "remember me" functionality
     * @return  bool Whether the login was successful
     */
    protected function _login(string|Model_User $user, string $password, bool $remember): bool
    {
        // If the user is not an object, fetch it from the database
        if (!is_object($user)) {
            $username = $user;
            $user = ORM::factory('User')
                ->where($user->unique_key($username), '=', $username)
                ->find();
        }

        // Hash the password for comparison
        $hashed_password = $this->hash($password);

        // Verify that the user has the 'login' role and the password matches
        if ($user->has('roles', ORM::factory('Role', ['name' => 'login'])) && $user->password === $hashed_password) {
            // If "remember me" is enabled, create a token and set a cookie
            if ($remember) {
                $data = [
                    'user_id'    => $user->pk(),
                    'expires'    => time() + $this->_config['lifetime'],
                    'user_agent' => sha1(Request::$user_agent),
                ];

                $token = ORM::factory('User_Token')
                    ->values($data)
                    ->create();

                Cookie::set('authautologin', $token->token, $this->_config['lifetime']);
            }

            // Complete the login process
            $this->complete_login($user);
            return true;
        }

        // Return false if login fails
        return false;
    }

    /**
     * Forces a user to be logged in without requiring a password.
     *
     * @param   string|Model_User $user Username as a string or a user ORM object
     * @param   bool $mark_session_as_forced Whether to mark the session as forced
     * @return  void
     */
    public function force_login(string|Model_User $user, bool $mark_session_as_forced = false): void
    {
        // Fetch the user from the database if not already an object
        if (!is_object($user)) {
            $username = $user;
            $user = ORM::factory('User')
                ->where($user->unique_key($username), '=', $username)
                ->find();
        }

        // Mark the session as forced if requested
        if ($mark_session_as_forced) {
            $this->_session->set('auth_forced', true);
        }

        // Complete the login process
        $this->complete_login($user);
    }

    /**
     * Logs in a user based on the "remember me" cookie.
     *
     * @return  mixed The user ORM object if successful, otherwise false
     */
    public function auto_login(): mixed
    {
        $token_value = Cookie::get('authautologin');

        if ($token_value) {
            $token = ORM::factory('User_Token', ['token' => $token_value]);

            // Verify the token and user
            if ($token->loaded() && $token->user->loaded()) {
                if ($token->user_agent === sha1(Request::$user_agent)) {
                    // Update and refresh the token
                    $token->save();
                    Cookie::set('authautologin', $token->token, $token->expires - time());
                    $this->complete_login($token->user);
                    return $token->user;
                }

                // Delete the token if invalid
                $token->delete();
            }
        }

        return false;
    }

    /**
     * Retrieves the currently logged-in user or attempts auto-login.
     *
     * @param   mixed $default Default value if no user is logged in
     * @return  mixed The user ORM object or the default value
     */
    public function get_user(mixed $default = null): mixed
    {
        $user = parent::get_user($default);

        if ($user === $default && ($user = $this->auto_login()) === false) {
            return $default;
        }

        return $user;
    }

    /**
     * Logs out the user and removes session data and tokens.
     *
     * @param   bool $destroy Whether to completely destroy the session
     * @param   bool $logout_all Whether to remove all autologin tokens for the user
     * @return  bool Whether the logout was successful
     */
    public function logout(bool $destroy = false, bool $logout_all = false): bool
    {
        // Remove the forced login flag
        $this->_session->delete('auth_forced');

        $token_value = Cookie::get('authautologin');

        if ($token_value) {
            Cookie::delete('authautologin');
            $token = ORM::factory('User_Token', ['token' => $token_value]);

            if ($token->loaded()) {
                // Delete all tokens if logout_all is true
                if ($logout_all) {
                    ORM::factory('User_Token')
                        ->where('user_id', '=', $token->user_id)
                        ->find_all()
                        ->each(fn($t) => $t->delete());
                } else {
                    $token->delete();
                }
            }
        }

        return parent::logout($destroy);
    }

    /**
     * Retrieves the hashed password for a user.
     *
     * @param   string|Model_User $user Username as a string or a user ORM object
     * @return  string|null The hashed password or null if not found
     */
    public function password(string|Model_User $user): ?string
    {
        if (!is_object($user)) {
            $user = ORM::factory('User')
                ->where($user->unique_key($user), '=', $user)
                ->find();
        }

        return $user->password ?? null;
    }

    /**
     * Completes the login process by updating the user and session data.
     *
     * @param   Model_User $user User ORM object
     * @return  void
     */
    protected function complete_login(Model_User $user): void
    {
        $user->complete_login();
        parent::complete_login($user);
    }

    /**
     * Verifies a password for the currently logged-in user.
     *
     * @param   string $password The plain-text password to verify
     * @return  bool Whether the password matches
     */
    public function check_password(string $password): bool
    {
        $user = $this->get_user();

        if (!$user) {
            return false;
        }

        return $this->hash($password) === $user->password;
    }
}
