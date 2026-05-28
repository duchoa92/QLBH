import mitt from 'mitt'

const emitter = mitt()

export function useEventBus() {

    /*
    |--------------------------------------------------------------------------
    | Emit
    |--------------------------------------------------------------------------
    */

    const emitEvent = (
        event,
        payload = null
    ) => {

        emitter.emit(
            event,
            payload
        )
    }

    /*
    |--------------------------------------------------------------------------
    | Listen
    |--------------------------------------------------------------------------
    */

    const onEvent = (
        event,
        callback
    ) => {

        emitter.on(
            event,
            callback
        )
    }

    /*
    |--------------------------------------------------------------------------
    | Remove
    |--------------------------------------------------------------------------
    */

    const offEvent = (
        event,
        callback
    ) => {

        emitter.off(
            event,
            callback
        )
    }

    return {

        emitEvent,

        onEvent,

        offEvent,
    }
}