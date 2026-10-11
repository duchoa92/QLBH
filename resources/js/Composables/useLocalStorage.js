export const useLocalStorage = () => {

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    const save = (key, value) => {

        try {

            localStorage.setItem(
                key,
                JSON.stringify(value)
            )

        } catch (error) {

            console.error('Không lưu được localStorage', error)
        }
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

        let data = null

        try {

            data = localStorage.getItem(key)

        } catch {

            return defaultValue
        }

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