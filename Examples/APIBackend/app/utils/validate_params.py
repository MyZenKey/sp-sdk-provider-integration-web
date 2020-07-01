import re
from werkzeug.exceptions import BadRequest

def validate_param(key, value):
    """
    raise an exception if a specific params fails validation (currently only the mccmnc)

    for mccmnc, validate that the value is 6 numerical digits
    """
    if key == 'mccmnc':
        if not re.match(r"^\d{6}$", value):
            raise BadRequest('%s is not a valid mccmnc' % value)

def parse_param(key, value):
    """
    validate and handle special formatting for specific params

    for the mccmnc param, cast to a string
    for the accr_values param create an array from space delimited string
    """
    parsed_value = value

    if key == 'mccmnc':
        parsed_value = str(value)
    elif key == 'accr_values':
        parsed_value = value.split(" ")
    else:
        return value

    validate_param(key, parsed_value)
    return parsed_value

def validate_params(request, required_params=None, optional_params=None):
    """
    extract all required and optional params from the request
    raise an exception if a required param is missing
    parse all params to make sure they're valid and correctly formatted
    """
    if required_params is None:
        required_params = []
    if optional_params is None:
        optional_params = []

    request_object = request.json if request.json else request.form
    params = {}

    for param in required_params:
        if param in request_object:
            params[param] = parse_param(param, request_object.get(param))
        else:
            raise BadRequest('%s param is missing' % param)

    for param in optional_params:
        if param in request_object:
            params[param] = parse_param(param, request_object.get(param))

    return params
