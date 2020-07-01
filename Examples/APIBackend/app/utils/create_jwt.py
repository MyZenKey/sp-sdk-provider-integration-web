from datetime import (datetime)

import jwt

def create_jwt(user, expiration_time, base_url, secret_key):
    """
    Create a new JWT containing the user attributes
    """
    now = datetime.utcnow()
    jwt_payload = {
        'name': user.get('name'),
        'email': user.get('email'),
        'postal_code': user.get('postal_code'),
        'phone_number': user.get('phone_number'),
        'zenkey_sub': user.get('zenkey_sub'),
        'exp': now + expiration_time,
        'iat': now,
        'nbf': now,
        'iss': base_url,
    }
    return jwt.encode(jwt_payload, secret_key, algorithm='HS256').decode('utf-8')
