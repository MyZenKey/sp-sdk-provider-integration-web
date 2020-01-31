import json
from json import JSONDecodeError

def normalize_port(port):
    """Normalize a port into a number or false."""
    if port is None:
        return False
    try:
        return int(port)
    except ValueError:
        return False

def get_current_user(flask_session):
    """get the current user info from the session"""
    if 'userinfo' in flask_session:
        try:
            return json.loads(flask_session['userinfo'])
        except JSONDecodeError:
            return None
    return None
