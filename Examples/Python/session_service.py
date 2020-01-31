
class SessionService:
    """
    a service for persisting items in the session
    """

    state_cache_key = "zenkey_state"
    mccmnc_cache_key = "zenkey_mccmnc"

    def __init__(self, session):
        self.session = session

    def clear(self):
        """
        clear the session storage
        """
        try:
            del self.session[self.state_cache_key]
        except KeyError:
            pass
        try:
            del self.session[self.mccmnc_cache_key]
        except KeyError:
            pass

    def set_state(self, state):
        """
        persist the state in the session
        """
        self.session[self.state_cache_key] = state

    def get_state(self):
        """
        get the state from the session
        """
        return self.session.get(self.state_cache_key)

    def set_mccmnc(self, mccmnc):
        """
        persist the MCCMNC in the session
        """
        self.session[self.mccmnc_cache_key] = mccmnc

    def get_mccmnc(self):
        """
        get the state from the session
        """
        return self.session.get(self.mccmnc_cache_key)
