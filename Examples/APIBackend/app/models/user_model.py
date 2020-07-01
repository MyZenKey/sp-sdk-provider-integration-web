import pdb

def gather_zenkey_values(raw_zenkey_attributes):
    sub = raw_zenkey_attributes.get('sub', None)
    if sub is None:
        sub = raw_zenkey_attributes.get('zenkey_sub', None)

    try:
        name = raw_zenkey_attributes['name']['value']
    except(AttributeError, KeyError, TypeError):
        name = None
    try:
        email = raw_zenkey_attributes['email']['value']
    except(AttributeError, KeyError, TypeError):
        email = None
    try:
        postal_code = raw_zenkey_attributes['postal_code']['value']
    except(AttributeError, KeyError, TypeError):
        postal_code = None
    try:
        phone_number = raw_zenkey_attributes['phone']['value']
    except(AttributeError, KeyError, TypeError):
        phone_number = None

    return {
        'zenkey_sub': sub,
        'name': name,
        'email': email,
        'postal_code': postal_code,
        'phone_number': phone_number,
    }

class UserModel():
    """
    This example class is used to interact with users stored in a fake database
    """
    @classmethod
    def find_zenkey_user(cls, raw_zenkey_attributes):
        """
        Look up a ZenKey user in the database based on the "sub" attribute.
        If no users with a matching "sub" exist in our database, return None

        This method is nonfunctional because this example app does not have a database.
        For demonstration purposes we've built it to always return a fake user built
        from the ZenKey attributes
        """
        # production code would be something like this:
        # return db.find('users', 'zenkey_sub', zenkey_attributes.get('sub'))

        # our fake user based on what we received from ZenKey:
        return {
            'user_id': 123,
            'username': 'Fake Username',
            **gather_zenkey_values(raw_zenkey_attributes)
        }

    @classmethod
    def find_user(cls, user_attributes):
        """
        look up a user in the database with matching attributes

        This method is nonfunctional because this example app does not have a database.
        For demonstration purposes we've built it to always return a fake user built
        from the ZenKey attributes we receive
        """
        # production code would be something like this:
        # return db.find_by('users', attributes)

         # our fake user based on the attributes passed to this method
        return {
            'user_id': 123,
            'username': 'Fake Username',
            'zenkey_sub': user_attributes.get('zenkey_sub'),
            'name': user_attributes.get('name'),
            'email': user_attributes.get('email'),
            'postal_code': user_attributes.get('postal_code'),
            'phone_number': user_attributes.get('phone_number'),
        }

    @classmethod
    def create_new_user(cls, user_attributes):
        """
        Create a new user in the database

        When creating a new user, the ZenKey "sub" value should be saved to associate the user with
        a unique ZenKey account. You should avoid having multiple users with the same ZenKey sub,
        unless you have a specific reason for doing so.

        This method is nonfunctional because this example app does not have a database.
        For demonstration purposes simply return a user with the attributes we received
        """
        # production code would be something like this:
        # return db.create('users', attributes)

         # our fake user based on the attributes passed to this method
        return {
            'user_id': 123,
            'username': 'Fake Username',
            'zenkey_sub': user_attributes.get('zenkey_sub'),
            'name': user_attributes.get('name'),
            'email': user_attributes.get('email'),
            'postal_code': user_attributes.get('postal_code'),
            'phone_number': user_attributes.get('phone_number'),
        }
