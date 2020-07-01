from werkzeug.exceptions import BadRequest

def validate_client_credentials(allowed_clients, client_id):
    """
    check if the client ID is allowed
    raise an exception if the client id is invalid
    return the an array of [client_id, client_secret] if the client id is valid
    """
    # look for the client_id in the list of allowed clients
    client_id_secret = next((client for client in allowed_clients if client[0] == client_id), None)
    # raise exception if client_id is not allowed
    if not client_id_secret:
        raise BadRequest('%s is not an allowed client_id' % client_id)
    # return a list [client_id, client_secret]
    return client_id_secret
