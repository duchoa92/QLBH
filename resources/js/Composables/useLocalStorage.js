export const useLocalStorage = () => {

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    const save = (key, value) => {

        localStorage.setItem(
            key,
            JSON.stringify(value)
        )
    }

    /*
    |--------------------------------------------------------------------------
    | Load
    |--------------------------------------------------------------------------
    */

    const load = (
        key,
        defaultValue = null
    ) => {

        const data =
            localStorage.getItem(key)

        if (!data) {

            return defaultValue
        }

        try {

            return JSON.parse(data)

        } catch {

            return defaultValue
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Remove
    |--------------------------------------------------------------------------
    */

    const remove = (key) => {

        localStorage.removeItem(key)
    }

    /*
    |--------------------------------------------------------------------------
    | Clear
    |--------------------------------------------------------------------------
    */

    const clear = () => {

        localStorage.clear()
    }

    return {

        save,

        load,

        remove,

        clear,
    }
}